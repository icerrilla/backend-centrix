<?php

namespace App\Http\Controllers;

use App\Exceptions\ValidacionNegocio;
use App\Services\AccesoNegocio;
use App\Services\ArchivosPrivados;
use App\Services\Bitacora;
use App\Support\ApiResponse;
use App\Support\EstadoCuenta;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BeneficioController extends Controller
{
    public function __construct(private AccesoNegocio $acceso) {}

    public function usados(int $id): int
    {
        return (int) DB::table('beneficios_aplicados')->where('beneficio_id', $id)->sum('cantidad');
    }

    public function publico(object $b): array
    {
        $usados = $this->usados($b->id);

        return array_replace((array) $b, ['activo' => (bool) $b->activo, 'usados' => $usados, 'avance' => round($usados / $b->disponibles * 100, 2), 'tipo_beneficio' => $this->acceso->resumen($b->tipo_beneficio_id, 'tipos_beneficio')]);
    }

    public function index(Request $r)
    {
        $in = $r->validate(['patrocinio_id' => ['sometimes', 'integer', 'min:1'], 'activo' => ['sometimes', 'boolean'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        abort_if(isset($in['activo']) && ! $in['activo'] && ! $this->acceso->puede($r, 'BENEFICIOS.CONSULTAR'), 403);
        $q = $this->acceso->query($r, 'beneficios', 'BENEFICIOS');
        foreach (['patrocinio_id', 'activo'] as $c) {
            if (isset($in[$c])) {
                $q->where($c, $in[$c]);
            }
        }

        return ApiResponse::make(200, 'Consulta correcta.', $this->acceso->pagina($q->orderByDesc('id')->paginate($in['per_page'] ?? 20), fn ($b) => $this->publico($b), 'beneficios'));
    }

    public function store(Request $r, Bitacora $b)
    {
        return $this->guardar($r, null, $b);
    }

    public function update(Request $r, int $id, Bitacora $b)
    {
        return $this->guardar($r, $id, $b);
    }

    private function guardar(Request $r, ?int $id, Bitacora $b)
    {
        return DB::transaction(function () use ($r, $id, $b) {
            $previo = $id ? $this->acceso->query($r, 'beneficios', 'BENEFICIOS')->where('id', $id)->lockForUpdate()->firstOrFail() : null;
            $datos = $r->validate(['patrocinio_id' => ['required', 'integer', 'exists:patrocinios,id'], 'nombre' => ['required', 'string', 'max:255'],
                'tipo_beneficio_id' => ['required', 'integer', Rule::exists('tipos_beneficio', 'id')->where(fn ($q) => $q->where('activo', true)->when($previo, fn ($q) => $q->orWhere('id', $previo->tipo_beneficio_id)))],
                'disponibles' => ['required', 'integer', 'min:1', 'max:4294967295'], 'vigencia_inicio' => ['required', 'date_format:Y-m-d'], 'vigencia_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:vigencia_inicio'], 'activo' => ['prohibited']]);
            if (! $this->acceso->puede($r, 'BENEFICIOS.CONSULTAR')) {
                $q = DB::table('patrocinios');
                $this->acceso->patrocinio($q, 'id', $r->user());
                $q->where('id', $datos['patrocinio_id'])->firstOrFail();
            }
            if ($previo) {
                if ($this->usados($id) > $datos['disponibles']) {
                    throw ValidacionNegocio::campo('disponibles', 'No puede ser menor que la cantidad ya utilizada.');
                }
                $usos = DB::table('beneficios_aplicados')->where('beneficio_id', $id);
                if ((clone $usos)->where(fn ($q) => $q->where('fecha', '<', $datos['vigencia_inicio'])->orWhere('fecha', '>', $datos['vigencia_fin']))->exists()) {
                    throw ValidacionNegocio::campo('vigencia_fin', 'La vigencia debe incluir todas las aplicaciones existentes.');
                }
                abort_if((clone $usos)->whereIn('evento_id', DB::table('eventos')->select('id')->where('patrocinio_id', '!=', $datos['patrocinio_id']))->exists(), 409, 'El beneficio tiene aplicaciones de eventos de otro patrocinio.');
            }
            $antes = $previo ? $this->publico($previo) : [];
            if ($id) {
                DB::table('beneficios')->where('id', $id)->update($datos + ['updated_at' => now()]);
            } else {
                $id = DB::table('beneficios')->insertGetId($datos + ['created_at' => now(), 'updated_at' => now()]);
            }
            $publico = $this->publico(DB::table('beneficios')->find($id));
            $b->contexto($r, 'BENEFICIOS', $previo ? 'EDITAR' : 'CREAR', 'Se '.($previo ? 'actualizó' : 'creó').' el beneficio «'.$datos['nombre'].'».');
            $b->detalle($r, 'BENEFICIO', $id, $antes, $publico);

            return ApiResponse::make($previo ? 200 : 201, $previo ? 'Beneficio actualizado correctamente.' : 'Beneficio creado correctamente.', $publico);
        });
    }

    public function estado(Request $r, int $id, Bitacora $b)
    {
        $activo = EstadoCuenta::validar($r);

        return DB::transaction(function () use ($r, $id, $b, $activo) {
            $q = DB::table('beneficios');
            if (! $this->acceso->puede($r, 'BENEFICIOS.CONSULTAR')) {
                $this->acceso->patrocinio($q, 'patrocinio_id', $r->user());
            }
            $beneficio = $q->where('id', $id)->lockForUpdate()->firstOrFail();
            DB::table('beneficios')->where('id', $id)->update(['activo' => $activo, 'updated_at' => now()]);
            $b->contexto($r, 'BENEFICIOS', $activo ? 'ACTIVAR' : 'INACTIVAR', 'Se '.($activo ? 'activó' : 'inactivó').' el beneficio «'.$beneficio->nombre.'».');
            $b->detalle($r, 'BENEFICIO', $id, ['activo' => (bool) $beneficio->activo], ['activo' => $activo]);

            return ApiResponse::make(200, 'Estado del beneficio actualizado correctamente.', $this->publico(DB::table('beneficios')->find($id)));
        });
    }

    private function aplicaciones(Request $r, int $beneficio): Builder
    {
        $q = DB::table('beneficios_aplicados')->where('beneficio_id', $beneficio);
        if (! $this->acceso->puede($r, 'BENEFICIOS.CONSULTAR')) {
            $this->acceso->eventoAsociadoVisible($q, $r);
        }

        return $q;
    }

    public function aplicadoPublico(object $a): array
    {
        return collect((array) $a)->except(['imagen_path', 'documento_path', 'evento_id'])->all() + ['evento' => $this->acceso->resumen($a->evento_id, 'eventos'),
            'imagen_url' => $a->imagen_path ? route('aplicados.imagen', ['id' => $a->id]) : null, 'documento_url' => $a->documento_path ? route('aplicados.documento', ['id' => $a->id]) : null];
    }

    public function aplicados(Request $r, int $id)
    {
        $this->acceso->query($r, 'beneficios', 'BENEFICIOS')->where('id', $id)->firstOrFail();

        return ApiResponse::make(200, 'Consulta correcta.', ['aplicados' => $this->aplicaciones($r, $id)->orderByDesc('fecha')->orderByDesc('id')->get()->map(fn ($a) => $this->aplicadoPublico($a))]);
    }

    public function registrar(Request $r, int $id, ArchivosPrivados $a, Bitacora $b)
    {
        return $this->guardarAplicado($r, $id, null, $a, $b);
    }

    public function editarAplicado(Request $r, int $id, ArchivosPrivados $a, Bitacora $b)
    {
        $previo = DB::table('beneficios_aplicados')->find($id);
        abort_unless($previo, 404);

        return $this->guardarAplicado($r, $previo->beneficio_id, $id, $a, $b);
    }

    private function guardarAplicado(Request $r, int $beneficioId, ?int $id, ArchivosPrivados $a, Bitacora $b)
    {
        return DB::transaction(function () use ($r, $beneficioId, $id, $a, $b) {
            // Todos los consumos y cambios de cupo bloquean el mismo padre.
            $beneficio = $this->acceso->query($r, 'beneficios', 'BENEFICIOS', false)->where('id', $beneficioId)->lockForUpdate()->firstOrFail();
            abort_unless($beneficio->activo, 409, 'El beneficio está inactivo y no admite aplicaciones.');
            $previo = $id ? $this->aplicaciones($r, $beneficioId)->where('id', $id)->lockForUpdate()->firstOrFail() : null;
            $in = $r->validate(['nombre' => ['required', 'string', 'max:255'], 'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$beneficio->vigencia_inicio, 'before_or_equal:'.$beneficio->vigencia_fin], 'cantidad' => ['required', 'integer', 'min:1', 'max:4294967295'],
                'evento_id' => ['nullable', 'integer', Rule::exists('eventos', 'id')->where('patrocinio_id', $beneficio->patrocinio_id)],
                'quitar_imagen' => ['sometimes', 'boolean'], 'quitar_documento' => ['sometimes', 'boolean']]);
            $in += ['evento_id' => null];
            $this->acceso->relacionEvento($r, $in['evento_id'], 'BENEFICIOS', $beneficio->patrocinio_id);
            if ($this->usados($beneficioId) - ($previo->cantidad ?? 0) + $in['cantidad'] > $beneficio->disponibles) {
                throw ValidacionNegocio::campo('cantidad', 'La cantidad supera los beneficios disponibles.');
            }
            $antes = $previo ? $this->aplicadoPublico($previo) : [];
            $datos = collect($in)->except(['quitar_imagen', 'quitar_documento'])->all();
            foreach (['imagen', 'documento'] as $campo) {
                if ($r->hasFile($campo) && $r->boolean('quitar_'.$campo)) {
                    throw ValidacionNegocio::campo($campo, 'No envíes un archivo y su eliminación al mismo tiempo.');
                }
                $columna = $campo.'_path';
                if ($r->has($campo) || $r->hasFile($campo)) {
                    $file = $a->guardar($r, $campo, 'aplicados/'.$beneficioId, $campo === 'imagen' ? ['jpg', 'jpeg', 'png', 'webp'] : ['pdf', 'jpg', 'jpeg', 'png', 'webp']);
                    $datos[$columna] = $file['ruta'];
                    if ($campo === 'documento') {
                        $datos['documento_nombre'] = $file['nombre'];
                    }
                    $a->borrar($previo->$columna ?? null);
                } elseif ($r->boolean('quitar_'.$campo)) {
                    $datos[$columna] = null;
                    if ($campo === 'documento') {
                        $datos['documento_nombre'] = null;
                    }
                    $a->borrar($previo->$columna ?? null);
                }
            }
            if ($id) {
                DB::table('beneficios_aplicados')->where('id', $id)->update($datos + ['updated_at' => now()]);
            } else {
                $id = DB::table('beneficios_aplicados')->insertGetId($datos + ['beneficio_id' => $beneficioId, 'created_at' => now(), 'updated_at' => now()]);
            }
            $publico = $this->aplicadoPublico(DB::table('beneficios_aplicados')->find($id));
            $b->contexto($r, 'BENEFICIOS', 'REGISTRAR', 'Se '.($previo ? 'actualizó' : 'registró').' la aplicación «'.$in['nombre'].'» del beneficio «'.$beneficio->nombre.'».');
            $b->detalle($r, 'BENEFICIO_APLICADO', $id, $antes, $publico);

            return ApiResponse::make($previo ? 200 : 201, $previo ? 'Aplicación actualizada correctamente.' : 'Aplicación registrada correctamente.', $publico);
        });
    }

    public function borrarAplicado(Request $r, int $id, ArchivosPrivados $a, Bitacora $b)
    {
        return DB::transaction(function () use ($r, $id, $a, $b) {
            $previo = DB::table('beneficios_aplicados')->find($id);
            abort_unless($previo, 404);
            $this->acceso->query($r, 'beneficios', 'BENEFICIOS')->where('id', $previo->beneficio_id)->lockForUpdate()->firstOrFail();
            $previo = $this->aplicaciones($r, $previo->beneficio_id)->where('id', $id)->lockForUpdate()->firstOrFail();
            $antes = $this->aplicadoPublico($previo);
            DB::table('beneficios_aplicados')->where('id', $id)->delete();
            $a->borrar($previo->imagen_path);
            $a->borrar($previo->documento_path);
            $b->contexto($r, 'BENEFICIOS', 'REGISTRAR', 'Se eliminó la aplicación «'.$previo->nombre.'» y se liberó su cantidad utilizada.');
            $b->detalle($r, 'BENEFICIO_APLICADO', $id, $antes, []);

            return ApiResponse::make(200, 'Aplicación eliminada correctamente.');
        });
    }

    public function archivo(Request $r, int $id, ArchivosPrivados $a)
    {
        $fila = DB::table('beneficios_aplicados')->find($id);
        abort_unless($fila, 404);
        $this->acceso->query($r, 'beneficios', 'BENEFICIOS')->where('id', $fila->beneficio_id)->firstOrFail();
        $fila = $this->aplicaciones($r, $fila->beneficio_id)->where('id', $id)->firstOrFail();
        $campo = $r->route()->defaults['campo'];

        return $a->respuesta($fila->{$campo.'_path'}, $campo === 'documento' ? $fila->documento_nombre : null);
    }
}
