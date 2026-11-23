<?php

namespace App\Services;

use App\Models\User;
use App\Support\CatalogosNegocio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Bitacora
{
    public function detalle(Request $request, string $tipo, ?int $id = null, array $anterior = [], array $nuevo = [], bool $persistidoEnError = false): void
    {
        $cambios = [];
        $prohibidos = ['password', 'password_confirmation', 'remember_token', 'token', 'reset_token'];
        foreach (array_unique([...array_keys($anterior), ...array_keys($nuevo)]) as $campo) {
            if (in_array($campo, $prohibidos, true)) {
                continue;
            }
            $antes = $anterior[$campo] ?? null;
            $despues = $nuevo[$campo] ?? null;
            $antes = json_decode(json_encode($antes, JSON_THROW_ON_ERROR), true);
            $despues = json_decode(json_encode($despues, JSON_THROW_ON_ERROR), true);
            if ($antes !== $despues) {
                $cambios[] = ['campo' => $campo, 'anterior' => $antes, 'nuevo' => $despues];
            }
        }
        $request->attributes->set('auditoria_detalle', ['registro_tipo' => $tipo, 'registro_id' => $id, 'cambios' => $cambios, 'persistido_en_error' => $persistidoEnError]);
    }

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
        $detalle = $request->attributes->get('auditoria_detalle', []);
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
            'registro_tipo' => $detalle['registro_tipo'] ?? $this->tipoDeRuta($request),
            'registro_id' => $detalle['registro_id'] ?? (is_numeric($request->route('id')) ? (int) $request->route('id') : null),
            'cambios' => json_encode(($status < 400 || ($status < 500 && ($detalle['persistido_en_error'] ?? false))) ? ($detalle['cambios'] ?? []) : [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip' => $request->ip(),
        ]);
        $request->attributes->set('bitacora_registro_id', $registroId);
    }

    private function contextoDeRuta(Request $request): array
    {
        $nombre = $request->route()?->getName();
        if ($request->route()?->defaults['catalogo'] ?? null) {
            $catalogo = CatalogosNegocio::todos()[$request->route()->defaults['catalogo']];

            return ['modulo' => $catalogo['modulo'], 'accion' => $this->accionRuta($nombre)];
        }
        foreach (['eventos' => 'EVENTOS', 'hitos' => 'HITOS', 'beneficios' => 'BENEFICIOS', 'aplicados' => 'BENEFICIOS', 'documentos' => 'DOCUMENTOS', 'configuracion' => 'CONFIGURACION'] as $prefijo => $modulo) {
            if (str_starts_with($nombre ?? '', $prefijo.'.')) {
                $accion = match (last(explode('.', $nombre))) {
                    'store' => $prefijo === 'documentos' ? 'SUBIR' : 'CREAR',
                    'update', 'reemplazar', 'borrarImagen' => 'EDITAR',
                    'imagen' => $request->isMethod('GET') ? 'CONSULTAR' : 'EDITAR',
                    'estado' => 'CAMBIAR_ESTADO', 'registrar', 'editar', 'destroy' => 'REGISTRAR',
                    'archivo' => $request->boolean('descargar') ? 'DESCARGAR' : 'VER', default => 'CONSULTAR',
                };

                return ['modulo' => $modulo, 'accion' => $accion];
            }
        }
        if (is_string($nombre) && str_starts_with($nombre, 'patrocinios.')) {
            return ['modulo' => 'PATROCINIOS', 'accion' => $this->accionRuta($nombre)];
        }
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

    private function accionRuta(?string $nombre): string
    {
        return match (last(explode('.', $nombre ?? ''))) {
            'index', 'show', 'imagen.ver' => 'CONSULTAR', 'store' => 'CREAR', 'update' => 'EDITAR',
            'estado' => 'CAMBIAR_ESTADO', 'destroy' => 'ELIMINAR', 'imagen', 'orden' => 'EDITAR', default => 'CONSULTAR',
        };
    }

    private function tipoDeRuta(Request $request): string
    {
        $nombre = $request->route()?->getName() ?? '';
        $catalogo = $request->route()?->defaults['catalogo'] ?? null;
        if ($catalogo) {
            return match ($catalogo) {
                'pilares' => 'PILAR', 'marcas' => 'MARCA', 'categorias' => 'CATEGORIA',
                'tipos-beneficio' => 'TIPO_BENEFICIO', 'embotelladores' => 'EMBOTELLADOR',
            };
        }

        return match (true) {
            str_starts_with($nombre, 'eventos.') => 'EVENTO',
            str_starts_with($nombre, 'hitos.') => 'HITO',
            str_starts_with($nombre, 'beneficios.') => 'BENEFICIO',
            str_starts_with($nombre, 'aplicados.') => 'BENEFICIO_APLICADO',
            str_starts_with($nombre, 'documentos.') => 'DOCUMENTO',
            str_starts_with($nombre, 'configuracion.') => 'CONFIGURACION',
            str_starts_with($nombre, 'usuarios.'), str_starts_with($nombre, 'auth.') => 'USUARIO',
            str_starts_with($nombre, 'roles.') => 'ROL',
            str_starts_with($nombre, 'patrocinios.') => 'PATROCINIO',
            in_array($nombre, ['permisos.index', 'modulos.index'], true) => 'PERMISO',
            $nombre === 'bitacora.index' => 'BITACORA', default => 'SISTEMA',
        };
    }
}
