<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Bitacora
{
    public function contexto(Request $request, string $modulo, string $accion, string $descripcion, ?string $resultado = null): void
    {
        $request->attributes->set('auditoria', compact('modulo', 'accion', 'descripcion', 'resultado'));
    }

    public function registrar(Request $request, int $status): void
    {
        $registroId = $request->attributes->get('bitacora_registro_id');
        // Una transacción puede revertir una inserción previa. No confundirla con
        // un registro confirmado y omitir la auditoría del error resultante.
        if ($registroId && DB::table('bitacora')->where('id', $registroId)->exists()) {
            return;
        }
        $contexto = array_replace($this->contextoDeRuta($request), $request->attributes->get('auditoria', []));
        $usuario = $request->user();
        $login = $request->attributes->get('login_auditoria', $usuario?->usuario ?? $usuario?->email);
        $accion = $contexto['accion'] ?? ($request->isMethod('GET') ? 'CONSULTAR' : 'SOLICITUD');
        $descripcion = $contexto['descripcion'] ?? ($status < 400
            ? ($request->isMethod('GET') ? 'Se consultó información del sistema.' : 'Se atendió una solicitud del sistema.')
            : 'No se pudo completar la solicitud.');
        if ($status >= 500) {
            $descripcion = 'No se pudo completar la solicitud por un error del servicio.';
        }
        if ($request->isMethod('OPTIONS')) {
            $accion = 'VERIFICAR_ORIGEN';
            $descripcion = $status < 400 ? 'Se verificó el acceso desde el navegador.' : 'Se rechazó la verificación de acceso desde el navegador.';
        }
        $registroId = DB::table('bitacora')->insertGetId([
            'usuario_id' => $usuario instanceof User && $usuario->exists ? $usuario->id : null,
            'usuario_nombre' => $usuario instanceof User ? trim($usuario->name.' '.$usuario->apellido_paterno.' '.$usuario->apellido_materno) : null,
            'usuario_login' => $login === null ? null : mb_substr((string) $login, 0, 255),
            'modulo' => $contexto['modulo'] ?? ($request->is('api/*') ? 'SISTEMA' : 'SITIO'),
            'accion' => $accion,
            'resultado' => $status >= 500 ? 'ERROR' : ($contexto['resultado'] ?? ($status < 400 ? 'EXITOSO' : 'RECHAZADO')),
            'descripcion' => $descripcion, 'created_at' => now(),
        ]);
        $request->attributes->set('bitacora_registro_id', $registroId);
    }

    private function contextoDeRuta(Request $request): array
    {
        $nombre = $request->route()?->getName();
        if (is_string($nombre) && str_starts_with($nombre, 'auth.')) {
            return ['modulo' => 'AUTENTICACION', 'accion' => match ($nombre) {
                'auth.login' => 'LOGIN', 'auth.logout' => 'LOGOUT',
                'auth.forgot-password' => 'RECUPERAR_ACCESO', 'auth.reset-password' => 'ESTABLECER_PASSWORD',
                default => 'CONSULTAR',
            }];
        }
        foreach (['usuarios.' => 'USUARIOS', 'roles.' => 'ROLES'] as $prefijo => $modulo) {
            if (is_string($nombre) && str_starts_with($nombre, $prefijo)) {
                $accion = substr($nombre, strlen($prefijo));

                return ['modulo' => $modulo, 'accion' => match ($accion) {
                    'index', 'show' => 'CONSULTAR', 'store' => 'CREAR', 'update' => 'EDITAR',
                    'estado' => 'CAMBIAR_ESTADO', 'destroy' => 'ELIMINAR',
                    'permisos' => 'ASIGNAR_PERMISOS', 'reenviar' => 'REENVIAR_ACCESO',
                    'desbloquear' => 'DESBLOQUEAR', default => 'SOLICITUD',
                }];
            }
        }

        return match (true) {
            in_array($nombre, ['permisos.index', 'modulos.index'], true) => ['modulo' => 'PERMISOS', 'accion' => 'CONSULTAR'],
            $nombre === 'bitacora.index' => ['modulo' => 'BITACORA', 'accion' => 'CONSULTAR'],
            $nombre === 'password.establecer' => ['modulo' => 'AUTENTICACION', 'accion' => 'CONSULTAR'],
            $request->is('up') => ['modulo' => 'SISTEMA', 'accion' => 'CONSULTAR'],
            default => [],
        };
    }
}
