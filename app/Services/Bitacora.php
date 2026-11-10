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
        if ($request->attributes->get('auditada')) {
            return;
        }
        $contexto = $request->attributes->get('auditoria', []);
        $usuario = $request->user();
        $login = $request->attributes->get('login_auditoria', $usuario?->usuario ?? $usuario?->email);
        $accion = $contexto['accion'] ?? ($request->isMethod('GET') ? 'CONSULTAR' : 'SOLICITUD');
        $descripcion = $contexto['descripcion'] ?? ($status < 400
            ? ($request->isMethod('GET') ? 'Se consultó información del sistema.' : 'Se atendió una solicitud del sistema.')
            : 'No se pudo completar la solicitud.');
        DB::table('bitacora')->insert([
            'usuario_id' => $usuario instanceof User && $usuario->exists ? $usuario->id : null,
            'usuario_nombre' => $usuario instanceof User ? trim($usuario->name.' '.$usuario->apellido_paterno.' '.$usuario->apellido_materno) : null,
            'usuario_login' => $login === null ? null : mb_substr((string) $login, 0, 255),
            'modulo' => $contexto['modulo'] ?? ($request->is('api/*') ? 'SISTEMA' : 'SITIO'),
            'accion' => $accion,
            'resultado' => $contexto['resultado'] ?? ($status < 400 ? 'EXITOSO' : ($status >= 500 ? 'ERROR' : 'RECHAZADO')),
            'descripcion' => $descripcion, 'created_at' => now(),
        ]);
        $request->attributes->set('auditada', true);
    }
}
