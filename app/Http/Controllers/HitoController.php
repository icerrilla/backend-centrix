<?php

namespace App\Http\Controllers;

use App\Services\AccesoNegocio;
use App\Services\Bitacora;
use App\Support\ApiResponse;
use App\Support\EstadoCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HitoController extends Controller
{
    public function __construct(private AccesoNegocio $acceso) {}

    public function publico(object $h): array
    {
        return array_replace((array) $h, ['activo' => (bool) $h->activo,
            'patrocinio' => $this->acceso->resumen($h->patrocinio_id, 'patrocinios', ['id', 'nombre', 'pilar_id']), 'evento' => $this->acceso->resumen($h->evento_id, 'eventos')]);
    }

    public function index(Request $r)
    {
        $in = $r->validate([
            'patrocinio_id' => ['sometimes', 'integer', 'min:1'], 'evento_id' => ['sometimes', 'integer', 'min:1'], 'pilar_id' => ['sometimes', 'integer', 'min:1'], 'activo' => ['sometimes', 'boolean'],
            'desde' => ['sometimes', 'date_format:Y-m-d'], 'hasta' => ['sometimes', 'date_format:Y-m-d', Rule::when($r->filled('desde'), 'after_or_equal:desde')],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        abort_if(isset($in['activo']) && ! $in['activo'] && ! $this->acceso->puede($r, 'HITOS.CONSULTAR'), 403);
        $q = $this->acceso->query($r, 'hitos', 'HITOS');
        foreach (['patrocinio_id', 'evento_id', 'activo'] as $c) {
            if (isset($in[$c])) {
                $q->where($c, $in[$c]);
            }
        }
        if (isset($in['pilar_id'])) {
            $q->whereIn('patrocinio_id', DB::table('patrocinios')->select('id')->where('pilar_id', $in['pilar_id']));
        }
        foreach (['desde' => '>=', 'hasta' => '<='] as $c => $op) {
            if (isset($in[$c])) {
                $q->where('fecha', $op, $in[$c]);
            }
        }

        return ApiResponse::make(200, 'Consulta correcta.', $this->acceso->pagina($q->orderBy('fecha')->orderBy('id')->paginate($in['per_page'] ?? 20), fn ($h) => $this->publico($h), 'hitos'));
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
            $previo = $id ? $this->acceso->query($r, 'hitos', 'HITOS')->where('id', $id)->lockForUpdate()->firstOrFail() : null;
            $datos = $r->validate(['nombre' => ['required', 'string', 'max:255'], 'fecha' => ['required', 'date_format:Y-m-d'], 'tipo' => ['required', Rule::in(['FECHA_LIMITE', 'ENTREGA', 'OTRO'])],
                'detalles' => ['nullable', 'string', 'max:20000'], 'patrocinio_id' => ['required', 'integer', 'exists:patrocinios,id'], 'evento_id' => ['nullable', 'integer', Rule::exists('eventos', 'id')->where('patrocinio_id', $r->input('patrocinio_id'))], 'activo' => ['prohibited']]);
            if (! $this->acceso->puede($r, 'HITOS.CONSULTAR')) {
                $q = DB::table('patrocinios');
                $this->acceso->patrocinio($q, 'id', $r->user());
                $q->where('id', $datos['patrocinio_id'])->firstOrFail();
            }
            $datos += ['evento_id' => null, 'detalles' => null];
            $this->acceso->relacionEvento($r, $datos['evento_id'], 'HITOS', (int) $datos['patrocinio_id'], false);
            if ($id) {
                DB::table('hitos')->where('id', $id)->update($datos + ['updated_at' => now()]);
            } else {
                $id = DB::table('hitos')->insertGetId($datos + ['created_at' => now(), 'updated_at' => now()]);
            }
            $publico = $this->publico(DB::table('hitos')->find($id));
            $b->contexto($r, 'HITOS', $previo ? 'EDITAR' : 'CREAR', 'Se '.($previo ? 'actualizó' : 'creó').' el hito «'.$datos['nombre'].'».');
            $b->detalle($r, 'HITO', $id, $previo ? $this->publico($previo) : [], $publico);

            return ApiResponse::make($previo ? 200 : 201, $previo ? 'Hito actualizado correctamente.' : 'Hito creado correctamente.', $publico);
        });
    }

    public function estado(Request $r, int $id, Bitacora $b)
    {
        $activo = EstadoCuenta::validar($r);

        return DB::transaction(function () use ($r, $id, $b, $activo) {
            // Retirar/restaurar no exige CONSULTAR; sí conserva el alcance del patrocinio.
            $q = DB::table('hitos');
            if (! $this->acceso->puede($r, 'HITOS.CONSULTAR')) {
                $this->acceso->patrocinio($q, 'patrocinio_id', $r->user());
            }
            $h = $q->where('id', $id)->lockForUpdate()->firstOrFail();
            DB::table('hitos')->where('id', $id)->update(['activo' => $activo, 'updated_at' => now()]);
            $b->contexto($r, 'HITOS', $activo ? 'RESTAURAR' : 'RETIRAR', 'Se '.($activo ? 'restauró' : 'retiró').' el hito «'.$h->nombre.'».');
            $b->detalle($r, 'HITO', $id, ['activo' => (bool) $h->activo], ['activo' => $activo]);

            return ApiResponse::make(200, 'Estado del hito actualizado correctamente.', $this->publico(DB::table('hitos')->find($id)));
        });
    }
}
