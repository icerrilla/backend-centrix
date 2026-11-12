<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\User;
use App\Services\Bitacora;
use App\Services\EnlacesPassword;
use App\Services\Sesiones;
use App\Support\ApiResponse;
use App\Support\EstadoCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(Request $request, Bitacora $bitacora)
    {
        $filtros = $request->validate([
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'], 'bloqueado' => ['sometimes', 'boolean'],
            'rol_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = User::with('rol')->orderBy('id');
        if (! empty($filtros['buscar'])) {
            $buscar = '%'.mb_strtolower($filtros['buscar']).'%';
            $query->where(function ($q) use ($buscar) {
                foreach (['name', 'apellido_paterno', 'apellido_materno', 'usuario', 'email'] as $campo) {
                    $q->orWhereRaw('LOWER('.$campo.') LIKE ?', [$buscar]);
                }
            });
        }
        foreach (['activo', 'bloqueado', 'rol_id'] as $campo) {
            if (array_key_exists($campo, $filtros)) {
                $query->where($campo, $filtros[$campo]);
            }
        }
        $pagina = $query->paginate($filtros['per_page'] ?? 20);
        $bitacora->contexto($request, 'USUARIOS', 'CONSULTAR', 'Se consultó el listado de usuarios.');

        return ApiResponse::make(200, 'Consulta correcta.', [
            'usuarios' => $pagina->getCollection()->map->datosPublicos()->values(),
            'paginacion' => ['pagina' => $pagina->currentPage(), 'por_pagina' => $pagina->perPage(), 'total' => $pagina->total(), 'ultima_pagina' => $pagina->lastPage()],
        ]);
    }

    public function show(Request $request, int $id, Bitacora $bitacora)
    {
        $usuario = User::findOrFail($id);
        $bitacora->contexto($request, 'USUARIOS', 'CONSULTAR', 'Se consultaron los datos del usuario '.$usuario->name.'.');

        return ApiResponse::make(200, 'Consulta correcta.', $usuario->datosPublicos());
    }

    public function store(Request $request, Bitacora $bitacora, EnlacesPassword $enlaces)
    {
        $datos = $this->datos($request);

        return DB::transaction(function () use ($request, $datos, $bitacora, $enlaces) {
            $this->validarRol($datos['rol_id']);
            $usuario = new User;
            $usuario->forceFill($datos + ['password' => Str::random(64), 'requiere_establecer_password' => true])->save();
            $enlaces->enviar($usuario);
            $bitacora->contexto($request, 'USUARIOS', 'CREAR', 'Se creó el usuario '.$usuario->name.' y se envió su enlace de acceso.');

            return ApiResponse::make(201, 'Usuario creado correctamente.', $usuario->datosPublicos());
        });
    }

    public function update(Request $request, int $id, Bitacora $bitacora, Sesiones $sesiones)
    {
        $usuario = User::findOrFail($id);
        $datos = $this->datos($request, $usuario);

        return DB::transaction(function () use ($request, $usuario, $datos, $bitacora, $sesiones) {
            $usuario = User::lockForUpdate()->findOrFail($usuario->id);
            $this->validarRol($datos['rol_id']);
            $this->protegerUltimoAdmin($usuario, $datos['rol_id'], $usuario->activo);
            $cambios = [];
            $etiquetas = ['name' => 'nombre', 'apellido_paterno' => 'apellido paterno', 'apellido_materno' => 'apellido materno', 'usuario' => 'usuario', 'email' => 'correo', 'rol_id' => 'rol'];
            foreach ($datos as $campo => $valor) {
                if ((string) $usuario->$campo !== (string) $valor) {
                    $anterior = $campo === 'rol_id' ? $usuario->rol?->nombre : $usuario->$campo;
                    $nuevo = $campo === 'rol_id' ? Rol::find($valor)?->nombre : $valor;
                    $cambios[] = $etiquetas[$campo].' de «'.($anterior ?? 'sin dato').'» a «'.($nuevo ?? 'sin dato').'»';
                }
            }
            $cambiaEmail = $usuario->email !== $datos['email'];
            if ($cambiaEmail) {
                Password::deleteToken($usuario);
                $usuario->email_verified_at = null;
            }
            $cambiaAcceso = $cambiaEmail || $usuario->usuario !== $datos['usuario'] || $usuario->rol_id !== (int) $datos['rol_id'];
            $usuario->forceFill($datos)->save();
            $usuario->unsetRelation('rol');
            if ($cambiaAcceso) {
                $sesiones->revocar($usuario);
            }
            $descripcion = $cambios ? 'Se actualizó '.implode('; ', $cambios).' del usuario '.$usuario->name.'.' : 'Se revisaron los datos del usuario '.$usuario->name.' sin cambios.';
            $bitacora->contexto($request, 'USUARIOS', 'EDITAR', $descripcion);

            return ApiResponse::make(200, 'Usuario actualizado correctamente.', $usuario->datosPublicos());
        });
    }

    public function estado(Request $request, int $id, Bitacora $bitacora, Sesiones $sesiones)
    {
        $activar = EstadoCuenta::validar($request);

        return DB::transaction(function () use ($request, $id, $bitacora, $sesiones, $activar) {
            $usuario = User::lockForUpdate()->findOrFail($id);
            $this->protegerUltimoAdmin($usuario, $usuario->rol_id, $activar);
            $usuario->activo = $activar;
            $usuario->save();
            if (! $activar) {
                $sesiones->revocar($usuario);
                Password::deleteToken($usuario);
            }
            $bitacora->contexto($request, 'USUARIOS', $activar ? 'ACTIVAR' : 'INACTIVAR', 'Se '.($activar ? 'activó' : 'inactivó').' la cuenta de '.$usuario->name.'.');

            return ApiResponse::make(200, 'Estado actualizado correctamente.', $usuario->datosPublicos());
        });
    }

    public function desbloquear(Request $request, int $id, Bitacora $bitacora)
    {
        return DB::transaction(function () use ($request, $id, $bitacora) {
            $usuario = User::lockForUpdate()->findOrFail($id);
            $usuario->forceFill(['bloqueado' => false, 'intentos_fallidos' => 0, 'bloqueado_at' => null])->save();
            $bitacora->contexto($request, 'USUARIOS', 'DESBLOQUEAR', 'Se desbloqueó la cuenta de '.$usuario->name.'.');

            return ApiResponse::make(200, 'Cuenta desbloqueada correctamente.', $usuario->datosPublicos());
        });
    }

    public function destroy(Request $request, int $id, Bitacora $bitacora, Sesiones $sesiones)
    {
        $request->validate(['confirmar' => ['required', 'accepted']]);

        return DB::transaction(function () use ($request, $id, $bitacora, $sesiones) {
            $usuario = User::lockForUpdate()->findOrFail($id);
            $this->protegerUltimoAdmin($usuario, null, false);
            // Las futuras relaciones de negocio deben usar FK restrictivas, nunca cascada.
            // Hoy solo existen tokens, sesiones, recuperación y la referencia nullable de bitácora.
            $sesiones->revocar($usuario);
            Password::deleteToken($usuario);
            $nombre = $usuario->name;
            $usuario->delete();
            if ($request->user()->id === $usuario->id) {
                $request->user()->exists = false;
            }
            $bitacora->contexto($request, 'USUARIOS', 'ELIMINAR', 'Se eliminó el usuario '.$nombre.'.');

            return ApiResponse::make(200, 'Usuario eliminado correctamente.');
        });
    }

    public function reenviar(Request $request, int $id, Bitacora $bitacora, EnlacesPassword $enlaces)
    {
        return DB::transaction(function () use ($request, $id, $bitacora, $enlaces) {
            $usuario = User::lockForUpdate()->findOrFail($id);
            abort_unless($usuario->activo && ! $usuario->bloqueado && $usuario->rol?->activo, 409, 'La cuenta no está disponible para recibir un enlace.');
            $estado = $enlaces->enviar($usuario);
            if ($estado === Password::RESET_THROTTLED) {
                return ApiResponse::make(429, 'Demasiados intentos. Intenta más tarde.');
            }
            $bitacora->contexto($request, 'USUARIOS', 'REENVIAR_ACCESO', 'Se envió un enlace de acceso al usuario '.$usuario->name.'.');

            return ApiResponse::make(200, 'Enlace enviado correctamente.');
        });
    }

    private function datos(Request $request, ?User $usuario = null): array
    {
        foreach (['usuario', 'email'] as $campo) {
            if (is_string($request->input($campo))) {
                $request->merge([$campo => mb_strtolower(trim($request->input($campo)))]);
            }
        }
        $unico = fn ($campo) => function ($attribute, $value, $fail) use ($campo, $usuario) {
            if (User::whereRaw('LOWER('.$campo.') = ?', [$value])->when($usuario, fn ($query) => $query->where('id', '!=', $usuario->id))->exists()) {
                $fail('El valor ya está registrado.');
            }
        };
        $validados = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido_paterno' => ['nullable', 'string', 'max:255'], 'apellido_materno' => ['nullable', 'string', 'max:255'],
            'usuario' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/', $unico('usuario')],
            'email' => ['required', 'email', 'max:255', $unico('email')],
            'rol_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('activo', true)],
            'password' => ['prohibited'], 'password_confirmation' => ['prohibited'],
            'activo' => ['prohibited'], 'bloqueado' => ['prohibited'], 'intentos_fallidos' => ['prohibited'],
        ]);

        return [
            'name' => $validados['nombre'], 'apellido_paterno' => $validados['apellido_paterno'] ?? null,
            'apellido_materno' => $validados['apellido_materno'] ?? null, 'usuario' => $validados['usuario'],
            'email' => $validados['email'], 'rol_id' => (int) $validados['rol_id'],
        ];
    }

    private function validarRol(int $id): void
    {
        abort_unless(Rol::whereKey($id)->where('activo', true)->lockForUpdate()->first(), 409, 'El rol no está disponible.');
    }

    private function protegerUltimoAdmin(User $usuario, ?int $rolId, bool $activo): void
    {
        $admin = Rol::where('codigo', 'ADMIN')->lockForUpdate()->first();
        if (! $admin || $usuario->rol_id !== $admin->id || ($activo && $rolId === $admin->id)) {
            return;
        }
        $otros = User::where('rol_id', $admin->id)->where('activo', true)->where('bloqueado', false)
            ->where('requiere_establecer_password', false)->where('id', '!=', $usuario->id)->lockForUpdate()->get();
        abort_if($otros->isEmpty(), 409, 'Debe permanecer al menos un administrador disponible.');
    }
}
