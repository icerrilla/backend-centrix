<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\PasswordBytes;
use App\Services\Bitacora;
use App\Services\EnlacesPassword;
use App\Services\Sesiones;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    public function forgot(Request $request, EnlacesPassword $enlaces, Bitacora $bitacora)
    {
        $input = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $request->attributes->set('login_auditoria', $input['email']);
        // Mismo resultado de configuración para cuentas existentes y desconocidas.
        $enlaces->validarConfiguracion();

        return DB::transaction(function () use ($request, $enlaces, $bitacora, $input) {
            $usuario = User::whereRaw('LOWER(email) = ?', [mb_strtolower($input['email'])])->lockForUpdate()->first();
            if ($usuario && $usuario->activo && ! $usuario->bloqueado && $usuario->rol?->activo) {
                $enlaces->enviar($usuario);
            }
            $bitacora->contexto($request, 'AUTENTICACION', 'RECUPERAR_ACCESO', 'Se solicitó un enlace para restablecer la contraseña.');
            $bitacora->registrar($request, 200);

            return ApiResponse::make(200, 'Si la cuenta está disponible, recibirás un enlace para establecer tu contraseña.');
        });
    }

    public function reset(Request $request, Sesiones $sesiones, Bitacora $bitacora)
    {
        $input = $request->validate([
            'email' => ['required', 'email', 'max:255'], 'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024', 'confirmed', new PasswordBytes, PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $request->attributes->set('login_auditoria', $input['email']);

        return DB::transaction(function () use ($request, $sesiones, $bitacora, $input) {
            $usuario = User::whereRaw('LOWER(email) = ?', [mb_strtolower($input['email'])])->lockForUpdate()->first();
            $valido = $usuario && $usuario->activo && ! $usuario->bloqueado && $usuario->rol?->activo
                && Password::tokenExists($usuario, $input['token']);
            if (! $valido) {
                $bitacora->contexto($request, 'AUTENTICACION', 'ESTABLECER_PASSWORD', 'Se rechazó el enlace para establecer la contraseña.', 'RECHAZADO');
                $bitacora->registrar($request, 422);

                return ApiResponse::make(422, 'El enlace no es válido o ya expiró.');
            }
            $usuario->forceFill(['password' => $input['password'], 'requiere_establecer_password' => false])->save();
            $sesiones->revocar($usuario);
            Password::deleteToken($usuario);
            // Restablecer contraseña no desbloquea ni reactiva cuentas.
            $bitacora->contexto($request, 'AUTENTICACION', 'ESTABLECER_PASSWORD', 'Se estableció la contraseña de la cuenta.');
            $bitacora->registrar($request, 200);

            return ApiResponse::make(200, 'Contraseña establecida correctamente.');
        });
    }
}
