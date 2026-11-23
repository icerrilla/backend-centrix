<?php

namespace App\Http\Controllers;

use App\Exceptions\ValidacionPublicacion;
use App\Models\Embotellador;
use App\Models\Patrocinio;
use App\Services\Bitacora;
use App\Services\Imagenes;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatrocinioController extends Controller
{
    public function index(Request $request, Bitacora $bitacora)
    {
        $input = $request->validate([
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pilar_id' => ['sometimes', 'integer', 'min:1'], 'ambito_id' => ['sometimes', 'integer', 'min:1'],
            'estado' => ['sometimes', Rule::in(['BORRADOR', 'PUBLICADO', 'ARCHIVADO'])],
            'marca_id' => ['sometimes', 'integer', 'min:1'],
            'vigencia_desde' => ['sometimes', 'date_format:Y-m-d'],
            'vigencia_hasta' => ['sometimes', 'date_format:Y-m-d', Rule::when($request->filled('vigencia_desde'), 'after_or_equal:vigencia_desde')],
            'orden' => ['sometimes', Rule::in(['nombre', '-nombre', 'vigencia_inicio', '-vigencia_inicio', 'inversion', '-inversion'])],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Patrocinio::visiblesPara($request->user())->with(['pilar', 'categoria', 'ambito', 'marcas', 'contactos']);
        foreach (['pilar_id', 'ambito_id', 'estado'] as $campo) {
            if (isset($input[$campo])) {
                $query->where($campo, $input[$campo]);
            }
        }
        if (! empty($input['buscar'])) {
            $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($input['buscar']).'%']);
        }
        if (isset($input['marca_id'])) {
            $query->whereHas('marcas', fn ($q) => $q->where('marcas.id', $input['marca_id']));
        }
        if (isset($input['vigencia_desde'])) {
            $query->where('vigencia_fin', '>=', $input['vigencia_desde']);
        }
        if (isset($input['vigencia_hasta'])) {
            $query->where('vigencia_inicio', '<=', $input['vigencia_hasta']);
        }
        if (isset($input['orden'])) {
            $query->orderBy(ltrim($input['orden'], '-'), str_starts_with($input['orden'], '-') ? 'desc' : 'asc');
        }
        $query->orderByDesc('id');
        $pagina = $query->paginate($input['per_page'] ?? 20);
        $bitacora->contexto($request, 'PATROCINIOS', 'CONSULTAR', 'Se consultó el listado de patrocinios.');

        return ApiResponse::make(200, 'Consulta correcta.', [
            'patrocinios' => $pagina->getCollection()->map->datosPublicos()->values(),
            'paginacion' => ['pagina' => $pagina->currentPage(), 'por_pagina' => $pagina->perPage(), 'total' => $pagina->total(), 'ultima_pagina' => $pagina->lastPage()],
        ]);
    }

    private function registro(Request $request, int $id, bool $bloquear = false): Patrocinio
    {
        $query = Patrocinio::visiblesPara($request->user())->whereKey($id);

        return ($bloquear ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public function show(Request $request, int $id, Bitacora $bitacora)
    {
        $registro = $this->registro($request, $id);
        $bitacora->contexto($request, 'PATROCINIOS', 'CONSULTAR', 'Se consultó el patrocinio «'.$registro->nombre.'».');

        return ApiResponse::make(200, 'Consulta correcta.', $registro->datosPublicos());
    }

    public function store(Request $request, Bitacora $bitacora)
    {
        $datos = $this->datos($request);

        return DB::transaction(function () use ($request, $bitacora, $datos) {
            $registro = new Patrocinio;
            $this->guardar($registro, $datos);
            $publico = $registro->datosPublicos();
            $bitacora->contexto($request, 'PATROCINIOS', 'CREAR', 'Se creó el patrocinio «'.$registro->nombre.'» como borrador.');
            $bitacora->detalle($request, 'PATROCINIO', $registro->id, [], $publico);

            return ApiResponse::make(201, 'Patrocinio creado correctamente.', $publico);
        });
    }

    public function update(Request $request, int $id, Bitacora $bitacora)
    {
        return DB::transaction(function () use ($request, $id, $bitacora) {
            $registro = $this->registro($request, $id, true);
            $antes = $registro->datosPublicos();
            $this->guardar($registro, $this->datos($request, $registro));
            $despues = $registro->datosPublicos();
            $bitacora->contexto($request, 'PATROCINIOS', 'EDITAR', 'Se actualizó el patrocinio «'.$registro->nombre.'».');
            $bitacora->detalle($request, 'PATROCINIO', $id, $antes, $despues);

            return ApiResponse::make(200, 'Patrocinio actualizado correctamente.', $despues);
        });
    }

    public function estado(Request $request, int $id, Bitacora $bitacora)
    {
        $input = $request->validate(['estado' => ['required', Rule::in(['BORRADOR', 'PUBLICADO', 'ARCHIVADO'])]]);
        $permiso = match ($input['estado']) {
            'PUBLICADO' => 'PUBLICAR', 'ARCHIVADO' => 'ARCHIVAR', default => 'EDITAR',
        };
        abort_unless($request->user()->tienePermiso('PATROCINIOS.'.$permiso) && $request->user()->currentAccessToken()->can('PATROCINIOS.'.$permiso), 403);

        return DB::transaction(function () use ($request, $id, $bitacora, $input, $permiso) {
            $registro = $this->registro($request, $id, true);
            $antes = $registro->estado;
            $permitidos = ['BORRADOR' => ['BORRADOR', 'PUBLICADO'], 'PUBLICADO' => ['PUBLICADO', 'ARCHIVADO', 'BORRADOR'], 'ARCHIVADO' => ['ARCHIVADO', 'BORRADOR']];
            abort_unless(in_array($input['estado'], $permitidos[$antes], true), 409, 'La transición de estado solicitada no está permitida.');
            if ($input['estado'] === 'PUBLICADO') {
                $this->validarPublicacion($registro);
            }
            $registro->estado = $input['estado'];
            $registro->save();
            $verbo = match ($input['estado']) {
                'PUBLICADO' => 'publicado', 'ARCHIVADO' => 'archivado', default => 'guardado como borrador'
            };
            $bitacora->contexto($request, 'PATROCINIOS', $input['estado'] === 'BORRADOR' ? 'EDITAR' : $permiso, 'Se cambió el patrocinio «'.$registro->nombre.'» a '.$input['estado'].'.');
            $bitacora->detalle($request, 'PATROCINIO', $id, ['estado' => $antes], ['estado' => $registro->estado]);

            return ApiResponse::make(200, 'Patrocinio '.$verbo.' correctamente.', $registro->datosPublicos());
        });
    }

    public function imagen(Request $request, int $id, Bitacora $bitacora, Imagenes $imagenes)
    {
        return DB::transaction(function () use ($request, $id, $bitacora, $imagenes) {
            $registro = $this->registro($request, $id, true);
            $anterior = $registro->imagen_path;
            $registro->imagen_path = $imagenes->guardar($request, 'patrocinios/'.$id, $anterior);
            $registro->save();
            $bitacora->contexto($request, 'PATROCINIOS', 'EDITAR', 'Se actualizó la imagen del patrocinio «'.$registro->nombre.'».');
            $bitacora->detalle($request, 'PATROCINIO', $id, ['imagen' => $anterior ? basename($anterior) : null], ['imagen' => basename($registro->imagen_path)]);

            return ApiResponse::make(200, 'Imagen actualizada correctamente.', $registro->datosPublicos());
        });
    }

    public function verImagen(Request $request, int $id, Bitacora $bitacora, Imagenes $imagenes)
    {
        $registro = $this->registro($request, $id);
        $bitacora->contexto($request, 'PATROCINIOS', 'CONSULTAR', 'Se consultó la imagen del patrocinio «'.$registro->nombre.'».');

        return $imagenes->respuesta($registro->imagen_path, true);
    }

    private function guardar(Patrocinio $registro, array $datos): void
    {
        $marcas = $datos['marca_ids'];
        $contactos = $datos['contactos'];
        unset($datos['marca_ids'], $datos['contactos']);
        $registro->forceFill($datos);
        if ($registro->estado === 'PUBLICADO') {
            $this->validarPublicacion($registro);
        }
        $registro->save();
        $registro->marcas()->sync($marcas);
        $registro->contactos()->delete();
        foreach ($contactos as $orden => $contacto) {
            $registro->contactos()->create($contacto + ['orden' => $orden]);
        }
        $registro->unsetRelations();
    }

    private function datos(Request $request, ?Patrocinio $actual = null): array
    {
        $referencia = fn ($tabla, $previo) => Rule::exists($tabla, 'id')->where(function ($query) use ($previo) {
            // Se permite conservar referencias inactivas ya usadas, nunca asignarlas de nuevo.
            $query->where(function ($q) use ($previo) {
                $q->where('activo', true);
                if ($previo) {
                    $q->orWhere('id', $previo);
                }
            });
        });
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'], 'descripcion' => ['nullable', 'string', 'max:20000'],
            'pilar_id' => ['required', 'integer', $referencia('pilares', $actual?->pilar_id)],
            'categoria_id' => ['nullable', 'integer', $referencia('categorias', $actual?->categoria_id)],
            'ambito_id' => ['sometimes', 'integer', $referencia('embotelladores', $actual?->ambito_id)],
            'marca_ids' => ['sometimes', 'array', 'list'], 'marca_ids.*' => ['required', 'integer', 'distinct', Rule::exists('marcas', 'id')->where(fn ($q) => $q->where('activo', true)->when($actual, fn ($q) => $q->orWhereIn('id', $actual->marcas->modelKeys())))],
            'contraparte' => ['nullable', 'string', 'max:255'], 'alcance' => ['nullable', 'string', 'max:255'],
            'ciudad_territorio' => ['nullable', 'string', 'max:255'], 'responsable' => ['nullable', 'string', 'max:255'],
            'vigencia_inicio' => ['nullable', 'date_format:Y-m-d'],
            'vigencia_fin' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('vigencia_inicio'), 'after_or_equal:vigencia_inicio')],
            'inversion' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'audiencia_promedio' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'contactos' => ['sometimes', 'array', 'list', 'max:100'], 'contactos.*' => ['array:nombre,email,rol'],
            'contactos.*.nombre' => ['required', 'string', 'max:255'], 'contactos.*.email' => ['nullable', 'email', 'max:255'], 'contactos.*.rol' => ['nullable', 'string', 'max:255'],
            'estado' => ['prohibited'], 'imagen_url' => ['prohibited'], 'imagen_path' => ['prohibited'],
        ]);
        $resultado = [];
        foreach (['nombre', 'descripcion', 'pilar_id', 'categoria_id', 'contraparte', 'alcance', 'ciudad_territorio', 'responsable', 'vigencia_inicio', 'vigencia_fin', 'inversion', 'audiencia_promedio'] as $campo) {
            $resultado[$campo] = $datos[$campo] ?? null;
        }
        $resultado['ambito_id'] = $datos['ambito_id'] ?? Embotellador::nacional()->id;
        $resultado['marca_ids'] = $datos['marca_ids'] ?? [];
        $resultado['contactos'] = $datos['contactos'] ?? [];

        return $resultado;
    }

    private function validarPublicacion(Patrocinio $registro): void
    {
        $faltantes = [];
        foreach (['nombre' => 'nombre', 'pilar_id' => 'pilar', 'categoria_id' => 'categoría', 'contraparte' => 'partner', 'ciudad_territorio' => 'ciudad/territorio', 'vigencia_inicio' => 'fecha de inicio', 'vigencia_fin' => 'fecha de fin', 'inversion' => 'inversión', 'responsable' => 'responsable', 'ambito_id' => 'embotellador'] as $campo => $etiqueta) {
            if ($registro->$campo === null || (is_string($registro->$campo) && trim($registro->$campo) === '')) {
                $faltantes[$campo] = $etiqueta;
            }
        }
        if ($faltantes) {
            throw ValidacionPublicacion::faltantes($faltantes);
        }
    }
}
