<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\PasswordBytes;
use App\Services\Bitacora;
use App\Services\Sesiones;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, Bitacora $bitacora, Sesiones $sesiones)
    {
        $input = $request->validate(['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:1024', new PasswordBytes]]);
        $login = mb_strtolower(trim($input['login']));
        $request->attributes->set('login_auditoria', $input['login']);

        return DB::transaction(function () use ($request, $bitacora, $sesiones, $input, $login) {
            // Los usuarios nuevos no admiten @: impide ambigüedad usuario/correo.
            $campo = str_contains($login, '@') ? 'email' : 'usuario';
            $usuario = User::whereRaw('LOWER('.$campo.') = ?', [$login])->lockForUpdate()->first();
            $bitacora->contexto($request, 'AUTENTICACION', 'LOGIN', 'No se pudo iniciar sesión.', 'RECHAZADO');
            // Hash de costo equivalente incluso cuando el identificador no existe.
            $correcta = Hash::check($input['password'], $usuario?->password ?? Hash::make('credencial-no-utilizable'));
            if (! $usuario || ! $correcta) {
                if ($usuario && $usuario->activo && ! $usuario->bloqueado) {
                    $usuario->intentos_fallidos = min(config('centrix.max_intentos'), $usuario->intentos_fallidos + 1);
                    if ($usuario->intentos_fallidos >= config('centrix.max_intentos')) {
                        $usuario->bloqueado = true;
                        $usuario->bloqueado_at = now();
                        $sesiones->revocar($usuario);
                        $bitacora->contexto($request, 'AUTENTICACION', 'LOGIN', 'Se bloqueó la cuenta por 3 intentos fallidos.', 'BLOQUEADO');
                    }
                    $usuario->save();
                    if ($usuario->bloqueado) {
                        $response = ApiResponse::make(423, 'La cuenta está bloqueada. Contacta al administrador.', null, null, 'La cuenta fue bloqueada por seguridad.');
                    }
                }
                $response ??= ApiResponse::make(401, 'Credenciales incorrectas.');
            } elseif ($usuario->bloqueado) {
                $bitacora->contexto($request, 'AUTENTICACION', 'LOGIN', 'Se rechazó el inicio de sesión porque la cuenta está bloqueada.', 'BLOQUEADO');
                $response = ApiResponse::make(423, 'La cuenta está bloqueada. Contacta al administrador.');
            } elseif (! $usuario->activo || ! $usuario->rol?->activo || $usuario->requiere_establecer_password) {
                $response = ApiResponse::make(403, 'La cuenta no está disponible.');
            } else {
                $sesiones->revocar($usuario);
                $usuario->forceFill(['intentos_fallidos' => 0, 'ultimo_acceso_at' => now(), 'bloqueado_at' => null])->save();
                $token = $usuario->createToken('centrix', ['*']);
                $response = ApiResponse::make(200, 'Inicio de sesión correcto.', [
                    'token' => $token->plainTextToken, 'token_type' => 'Bearer',
                    'expires_in' => config('centrix.inactividad_segundos'), 'usuario' => $usuario->datosPublicos(true),
                ]);
                $bitacora->contexto($request, 'AUTENTICACION', 'LOGIN', 'Se inició sesión correctamente.', 'EXITOSO');
            }
            // Un intento fallido no acredita la identidad del autor; se conserva el login.
            if ($usuario && $correcta && $response->getStatusCode() === 200) {
                $request->setUserResolver(fn () => $usuario);
            }
            $bitacora->registrar($request, $response->getStatusCode());

            return $response;
        });
    }

    public function me(Request $request, Bitacora $bitacora)
    {
        $bitacora->contexto($request, 'AUTENTICACION', 'CONSULTAR', 'Se consultaron los datos de la sesión.');

        return ApiResponse::make(200, 'Consulta correcta.', ['usuario' => $request->user()->datosPublicos(true)]);
    }

    public function logout(Request $request, Bitacora $bitacora, Sesiones $sesiones)
    {
        $sesiones->revocar($request->user());
        $bitacora->contexto($request, 'AUTENTICACION', 'LOGOUT', 'Se cerró la sesión.');

        return ApiResponse::make(200, 'Sesión cerrada correctamente.');
    }
}
