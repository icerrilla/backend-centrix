<?php

namespace App\Http\Controllers;

use App\Services\AccesoNegocio;
use App\Services\ArchivosPrivados;
use App\Services\Bitacora;
use App\Support\ApiResponse;
use App\Support\EstadosMexico;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventoController extends Controller
{
    public function __construct(private AccesoNegocio $acceso) {}

    public function estadoCalculado(object $e): string
    {
        return $e->estado === 'CANCELADO' ? 'CANCELADO' : ($e->fecha_hora < now('UTC')->format('Y-m-d H:i:s') ? 'REALIZADO' : 'PROGRAMADO');
    }

    public function publico(object $e): array
    {
        return collect((array) $e)->except(['fecha_hora', 'estado'])->all() + [
            'fecha_hora' => Carbon::parse($e->fecha_hora, 'UTC')->toISOString(), 'estado' => $this->estadoCalculado($e),
            'patrocinio' => $this->acceso->resumen($e->patrocinio_id, 'patrocinios', ['id', 'nombre', 'pilar_id']),
            'ambito' => $this->acceso->resumen($e->ambito_id, 'embotelladores'),
            'marcas' => DB::table('marcas')->whereIn('id', DB::table('evento_marca')->where('evento_id', $e->id)->select('marca_id'))->get(['id', 'nombre']),
            'marca_ids' => DB::table('evento_marca')->where('evento_id', $e->id)->pluck('marca_id'),
            'imagenes' => DB::table('evento_imagenes')->where('evento_id', $e->id)->orderBy('id')->get(['id'])->map(fn ($i) => ['id' => $i->id, 'url' => route('eventos.imagen.ver', ['id' => $e->id, 'imagen_id' => $i->id])]),
        ];
    }

    public function index(Request $r)
    {
        $in = $r->validate([
            'patrocinio_id' => ['sometimes', 'integer', 'min:1'], 'pilar_id' => ['sometimes', 'integer', 'min:1'], 'ambito_id' => ['sometimes', 'integer', 'min:1'], 'marca_id' => ['sometimes', 'integer', 'min:1'],
            'estado' => ['sometimes', Rule::in(['PROGRAMADO', 'CANCELADO', 'REALIZADO'])], 'buscar' => ['nullable', 'string', 'max:100'],
            'desde' => ['sometimes', ...$this->acceso->reglaInstante()], 'hasta' => ['sometimes', ...$this->acceso->reglaInstante(), Rule::when($r->filled('desde'), 'after_or_equal:desde')],
            'orden' => ['sometimes', Rule::in(['fecha_hora', '-fecha_hora'])], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $q = $this->acceso->eventos($r);
        foreach (['patrocinio_id', 'ambito_id'] as $c) {
            if (isset($in[$c])) {
                $q->where($c, $in[$c]);
            }
        }
        if (isset($in['pilar_id'])) {
            $q->whereIn('patrocinio_id', DB::table('patrocinios')->select('id')->where('pilar_id', $in['pilar_id']));
        }
        if (isset($in['marca_id'])) {
            $q->whereIn('id', DB::table('evento_marca')->select('evento_id')->where('marca_id', $in['marca_id']));
        }
        if (! empty($in['buscar'])) {
            $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($in['buscar']).'%']);
        }
        foreach (['desde' => '>=', 'hasta' => '<='] as $c => $op) {
            if (isset($in[$c])) {
                $q->where('fecha_hora', $op, $this->acceso->fechaUTC($in[$c]));
            }
        }
        if (isset($in['estado'])) {
            if ($in['estado'] === 'CANCELADO') {
                $q->where('estado', 'CANCELADO');
            } else {
                $q->where('estado', 'PROGRAMADO')->where('fecha_hora', $in['estado'] === 'REALIZADO' ? '<' : '>=', now('UTC')->format('Y-m-d H:i:s'));
            }
        }
        $p = $q->orderBy('fecha_hora', ($in['orden'] ?? '') === '-fecha_hora' ? 'desc' : 'asc')->orderBy('id')->paginate($in['per_page'] ?? 20);

        return ApiResponse::make(200, 'Consulta correcta.', $this->acceso->pagina($p, fn ($e) => $this->publico($e), 'eventos'));
    }

    public function show(Request $r, int $id)
    {
        return ApiResponse::make(200, 'Consulta correcta.', $this->publico($this->acceso->eventos($r)->where('id', $id)->firstOrFail()));
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
            $previo = $id ? $this->acceso->eventos($r)->where('id', $id)->lockForUpdate()->firstOrFail() : null;
            $antes = $previo ? $this->publico($previo) : [];
            $in = $r->validate([
                'patrocinio_id' => ['required', 'integer', 'exists:patrocinios,id'], 'nombre' => ['required', 'string', 'max:255'],
                'fecha_hora' => ['required', ...$this->acceso->reglaInstante()],
                'sede' => ['nullable', 'string', 'max:255'], 'ciudad' => ['nullable', 'string', 'max:255'], 'responsable' => ['nullable', 'string', 'max:255'],
                'estado_republica' => ['nullable', Rule::in(EstadosMexico::CLAVES)],
                'equipo_local' => ['nullable', 'required_with:equipo_visitante', 'string', 'max:255'], 'equipo_visitante' => ['nullable', 'required_with:equipo_local', 'string', 'max:255'],
                'asistencia_estimada' => ['nullable', 'integer', 'min:0', 'max:4294967295'], 'ambito_id' => ['sometimes', 'integer', Rule::exists('embotelladores', 'id')->where(fn ($q) => $q->where('activo', true)->when($previo, fn ($q) => $q->orWhere('id', $previo->ambito_id)))],
                'marca_ids' => ['sometimes', 'array', 'list'], 'marca_ids.*' => ['integer', 'distinct', Rule::exists('marcas', 'id')->where(fn ($q) => $q->where('activo', true)->when($id, fn ($q) => $q->orWhereIn('id', DB::table('evento_marca')->where('evento_id', $id)->select('marca_id'))))], 'estado' => ['prohibited'],
            ]);
            $patrocinio = DB::table('patrocinios')->where('id', $in['patrocinio_id'])->firstOrFail();
            $datos = array_fill_keys(['sede', 'ciudad', 'estado_republica', 'equipo_local', 'equipo_visitante', 'responsable', 'asistencia_estimada'], null);
            $datos = array_replace($datos, collect($in)->except('marca_ids')->all());
            $datos['ambito_id'] ??= $patrocinio->ambito_id;
            if (! $this->acceso->puede($r, 'EVENTOS.CONSULTAR')) {
                abort_unless($patrocinio->estado === 'PUBLICADO' && in_array($datos['ambito_id'], $r->user()->ambitoIdsVisibles()), 404);
            }
            $datos['fecha_hora'] = $this->acceso->fechaUTC($in['fecha_hora']);
            $datos['updated_at'] = now();
            if ($id) {
                DB::table('eventos')->where('id', $id)->update($datos);
            } else {
                $id = DB::table('eventos')->insertGetId($datos + ['created_at' => now()]);
            }
            // No mover un evento a otro patrocinio si dejaría relaciones incompatibles.
            foreach (['hitos', 'documentos'] as $tabla) {
                abort_if(DB::table($tabla)->where('evento_id', $id)->where('patrocinio_id', '!=', $datos['patrocinio_id'])->exists(), 409, 'El evento tiene registros de otro patrocinio y no se puede trasladar.');
            }
            abort_if(DB::table('beneficios_aplicados')->join('beneficios', 'beneficios.id', '=', 'beneficios_aplicados.beneficio_id')->where('evento_id', $id)->where('patrocinio_id', '!=', $datos['patrocinio_id'])->exists(), 409, 'El evento tiene beneficios de otro patrocinio y no se puede trasladar.');
            DB::table('evento_marca')->where('evento_id', $id)->delete();
            foreach ($in['marca_ids'] ?? [] as $marca) {
                DB::table('evento_marca')->insert(['evento_id' => $id, 'marca_id' => $marca]);
            }
            $publico = $this->publico(DB::table('eventos')->find($id));
            $b->contexto($r, 'EVENTOS', $previo ? 'EDITAR' : 'CREAR', 'Se '.($previo ? 'actualizó' : 'creó').' el evento «'.$datos['nombre'].'».');
            $b->detalle($r, 'EVENTO', $id, $antes, $publico);

            return ApiResponse::make($previo ? 200 : 201, $previo ? 'Evento actualizado correctamente.' : 'Evento creado correctamente.', $publico);
        });
    }

    public function estado(Request $r, int $id, Bitacora $b)
    {
        $in = $r->validate(['estado' => ['required', Rule::in(['PROGRAMADO', 'CANCELADO'])]]);

        return DB::transaction(function () use ($r, $id, $b, $in) {
            $e = $this->acceso->eventos($r)->where('id', $id)->lockForUpdate()->firstOrFail();
            DB::table('eventos')->where('id', $id)->update($in + ['updated_at' => now()]);
            $publico = $this->publico(DB::table('eventos')->find($id));
            $b->contexto($r, 'EVENTOS', 'CANCELAR', 'Se cambió el evento «'.$e->nombre.'» a '.$publico['estado'].'.');
            $b->detalle($r, 'EVENTO', $id, ['estado' => $this->estadoCalculado($e)], ['estado' => $publico['estado']]);

            return ApiResponse::make(200, 'Estado del evento actualizado correctamente.', $publico);
        });
    }

    public function imagen(Request $r, int $id, ArchivosPrivados $a, Bitacora $b)
    {
        return DB::transaction(function () use ($r, $id, $a, $b) {
            $e = $this->acceso->eventos($r)->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_if(DB::table('evento_imagenes')->where('evento_id', $id)->count() >= 20, 409, 'El evento admite hasta 20 imágenes.');
            $file = $a->guardar($r, 'archivo', 'eventos/'.$id, ['jpg', 'jpeg', 'png', 'webp']);
            $imagen = DB::table('evento_imagenes')->insertGetId(['evento_id' => $id, 'archivo_path' => $file['ruta'], 'created_at' => now(), 'updated_at' => now()]);
            $b->contexto($r, 'EVENTOS', 'EDITAR', 'Se agregó una imagen al evento «'.$e->nombre.'».');
            $b->detalle($r, 'EVENTO', $id, [], ['imagen_id' => $imagen]);

            return ApiResponse::make(201, 'Imagen agregada correctamente.', $this->publico($e));
        });
    }

    public function verImagen(Request $r, int $id, int $imagen_id, ArchivosPrivados $a)
    {
        $this->acceso->eventos($r)->where('id', $id)->firstOrFail();
        $i = DB::table('evento_imagenes')->where('evento_id', $id)->where('id', $imagen_id)->firstOrFail();

        return $a->respuesta($i->archivo_path);
    }

    public function borrarImagen(Request $r, int $id, int $imagen_id, ArchivosPrivados $a, Bitacora $b)
    {
        return DB::transaction(function () use ($r, $id, $imagen_id, $a, $b) {
            $e = $this->acceso->eventos($r)->where('id', $id)->lockForUpdate()->firstOrFail();
            $i = DB::table('evento_imagenes')->where('evento_id', $id)->where('id', $imagen_id)->firstOrFail();
            DB::table('evento_imagenes')->where('id', $imagen_id)->delete();
            $a->borrar($i->archivo_path);
            $b->contexto($r, 'EVENTOS', 'EDITAR', 'Se eliminó una imagen del evento «'.$e->nombre.'».');
            $b->detalle($r, 'EVENTO', $id, ['imagen_id' => $imagen_id], []);

            return ApiResponse::make(200, 'Imagen eliminada correctamente.');
        });
    }
}
