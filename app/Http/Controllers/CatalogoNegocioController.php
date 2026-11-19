<?php

namespace App\Http\Controllers;

use App\Services\Bitacora;
use App\Services\Imagenes;
use App\Support\ApiResponse;
use App\Support\CatalogosNegocio;
use App\Support\EstadoCuenta;
use App\Support\EstadosMexico;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogoNegocioController extends Controller
{
    private function configuracion(Request $request): array
    {
        return CatalogosNegocio::todos()[$request->route()->defaults['catalogo']];
    }

    private function registro(Request $request, bool $lectura = false): object
    {
        $config = $this->configuracion($request);
        $query = DB::table($config['tabla'])->where('id', $request->route('id'));
        if ($lectura && ! $this->puedeConsultar($request)) {
            $query->where('activo', true);
        }
        $registro = $query->first();
        abort_unless($registro, 404);

        return $registro;
    }

    private function publico(Request $request, object $registro): array
    {
        $datos = (array) $registro;
        $datos['activo'] = (bool) $registro->activo;
        $catalogo = $request->route()->defaults['catalogo'];
        if (property_exists($registro, 'imagen_path')) {
            $datos[$catalogo === 'marcas' ? 'logotipo_url' : 'imagen_url'] = $registro->imagen_path
                ? route('catalogos.'.$catalogo.'.imagen.ver', ['id' => $registro->id, 'v' => pathinfo($registro->imagen_path, PATHINFO_FILENAME)]) : null;
            unset($datos['imagen_path']);
        }
        if ($catalogo === 'embotelladores') {
            $datos['estados'] = json_decode($registro->estados, true);
            $datos['es_nacional'] = (bool) $registro->es_nacional;
            unset($datos['clave_sistema']);
        }

        return $datos;
    }

    public function index(Request $request, Bitacora $bitacora)
    {
        $config = $this->configuracion($request);
        $input = $request->validate([
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'], 'activo' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $puedeConsultar = $this->puedeConsultar($request);
        abort_if(! $puedeConsultar && isset($input['activo']) && ! (bool) $input['activo'], 403);
        $query = DB::table($config['tabla']);
        if (! $puedeConsultar) {
            $query->where('activo', true);
        } elseif (array_key_exists('activo', $input)) {
            $query->where('activo', $input['activo']);
        }
        if (! empty($input['buscar'])) {
            $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($input['buscar']).'%']);
        }
        $query->orderBy($config['tabla'] === 'pilares' ? 'orden' : 'nombre')->orderBy('id');
        $pagina = $query->paginate($input['per_page'] ?? 20);
        $bitacora->contexto($request, $config['modulo'], 'CONSULTAR', 'Se consultó el catálogo de '.$request->route()->defaults['catalogo'].'.');

        return ApiResponse::make(200, 'Consulta correcta.', [
            $config['tabla'] => $pagina->getCollection()->map(fn ($fila) => $this->publico($request, $fila))->values(),
            'paginacion' => ['pagina' => $pagina->currentPage(), 'por_pagina' => $pagina->perPage(), 'total' => $pagina->total(), 'ultima_pagina' => $pagina->lastPage()],
        ]);
    }

    public function show(Request $request, Bitacora $bitacora)
    {
        $config = $this->configuracion($request);
        $registro = $this->registro($request, true);
        $bitacora->contexto($request, $config['modulo'], 'CONSULTAR', 'Se consultó '.$config['singular'].' «'.$registro->nombre.'».');

        return ApiResponse::make(200, 'Consulta correcta.', $this->publico($request, $registro));
    }

    public function store(Request $request, Bitacora $bitacora)
    {
        $config = $this->configuracion($request);
        $datos = $this->datos($request);

        return DB::transaction(function () use ($request, $bitacora, $config, $datos) {
            $ahora = now();
            if ($config['tabla'] === 'pilares') {
                $datos['orden'] ??= ((int) DB::table('pilares')->max('orden')) + 1;
            }
            $id = DB::table($config['tabla'])->insertGetId($datos + ['created_at' => $ahora, 'updated_at' => $ahora]);
            $registro = DB::table($config['tabla'])->find($id);
            $publico = $this->publico($request, $registro);
            $bitacora->contexto($request, $config['modulo'], 'CREAR', 'Se creó '.$config['singular'].' «'.$registro->nombre.'».');
            $bitacora->detalle($request, $this->tipo($request), $id, [], $publico);

            return ApiResponse::make(201, ucfirst($config['singular']).(in_array($config['tabla'], ['marcas', 'categorias'], true) ? ' creada' : ' creado').' correctamente.', $publico);
        });
    }

    public function update(Request $request, Bitacora $bitacora)
    {
        $config = $this->configuracion($request);

        return DB::transaction(function () use ($request, $bitacora, $config) {
            $registro = DB::table($config['tabla'])->where('id', $request->route('id'))->lockForUpdate()->first();
            abort_unless($registro, 404);
            $antes = $this->publico($request, $registro);
            $datos = $this->datos($request, $registro);
            DB::table($config['tabla'])->where('id', $registro->id)->update($datos + ['updated_at' => now()]);
            $despues = $this->publico($request, DB::table($config['tabla'])->find($registro->id));
            $bitacora->contexto($request, $config['modulo'], 'EDITAR', 'Se actualizó '.$config['singular'].' «'.$despues['nombre'].'».');
            $bitacora->detalle($request, $this->tipo($request), $registro->id, $antes, $despues);

            return ApiResponse::make(200, 'Registro actualizado correctamente.', $despues);
        });
    }

    public function estado(Request $request, Bitacora $bitacora)
    {
        $config = $this->configuracion($request);
        $activo = EstadoCuenta::validar($request);

        return DB::transaction(function () use ($request, $bitacora, $config, $activo) {
            $registro = DB::table($config['tabla'])->where('id', $request->route('id'))->lockForUpdate()->first();
            abort_unless($registro, 404);
            abort_if($config['tabla'] === 'embotelladores' && $registro->es_nacional && ! $activo, 409, 'Nacional no se puede desactivar.');
            DB::table($config['tabla'])->where('id', $registro->id)->update(['activo' => $activo, 'updated_at' => now()]);
            $bitacora->contexto($request, $config['modulo'], $activo ? 'ACTIVAR' : 'INACTIVAR', 'Se '.($activo ? 'activó' : 'inactivó').' '.$config['singular'].' «'.$registro->nombre.'».');
            $bitacora->detalle($request, $this->tipo($request), $registro->id, ['activo' => (bool) $registro->activo], ['activo' => $activo]);

            return ApiResponse::make(200, 'Estado actualizado correctamente.', $this->publico($request, DB::table($config['tabla'])->find($registro->id)));
        });
    }

    public function destroy(Request $request, Bitacora $bitacora)
    {
        $config = $this->configuracion($request);
        try {
            return DB::transaction(function () use ($request, $bitacora, $config) {
                $registro = DB::table($config['tabla'])->where('id', $request->route('id'))->lockForUpdate()->first();
                abort_unless($registro, 404);
                abort_if($config['tabla'] === 'embotelladores' && $registro->es_nacional, 409, 'Nacional no se puede eliminar.');
                $referencias = match ($config['tabla']) {
                    'pilares' => [['patrocinios', 'pilar_id']],
                    'categorias' => [['patrocinios', 'categoria_id']],
                    'marcas' => [['patrocinio_marca', 'marca_id']],
                    'embotelladores' => [['patrocinios', 'ambito_id'], ['usuario_embotellador', 'embotellador_id']],
                    default => [],
                };
                foreach ($referencias as [$tabla, $campo]) {
                    abort_if(DB::table($tabla)->where($campo, $registro->id)->exists(), 409, 'El registro está en uso y no se puede eliminar.');
                }
                $antes = $this->publico($request, $registro);
                DB::table($config['tabla'])->where('id', $registro->id)->delete();
                if (! empty($registro->imagen_path)) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($registro->imagen_path));
                }
                $bitacora->contexto($request, $config['modulo'], 'ELIMINAR', 'Se eliminó '.$config['singular'].' «'.$registro->nombre.'».');
                $bitacora->detalle($request, $this->tipo($request), $registro->id, $antes, []);

                return ApiResponse::make(200, 'Registro eliminado correctamente.');
            });
        } catch (QueryException $exception) {
            if (in_array((int) ($exception->errorInfo[1] ?? 0), [1451, 787], true)) {
                abort(409, 'El registro está en uso y no se puede eliminar.');
            }
            throw $exception;
        }
    }

    public function imagen(Request $request, Bitacora $bitacora, Imagenes $imagenes)
    {
        $config = $this->configuracion($request);

        return DB::transaction(function () use ($request, $bitacora, $imagenes, $config) {
            $registro = DB::table($config['tabla'])->where('id', $request->route('id'))->lockForUpdate()->first();
            abort_unless($registro, 404);
            $antes = $this->publico($request, $registro);
            $ruta = $imagenes->guardar($request, 'catalogos/'.$config['tabla'].'/'.$registro->id, $registro->imagen_path);
            DB::table($config['tabla'])->where('id', $registro->id)->update(['imagen_path' => $ruta, 'updated_at' => now()]);
            $despues = $this->publico($request, DB::table($config['tabla'])->find($registro->id));
            // La URL pública es estable; el cambio de imagen se registra sin exponer rutas privadas.
            $campo = $config['tabla'] === 'marcas' ? 'logotipo_url' : 'imagen_url';
            $bitacora->contexto($request, $config['modulo'], 'EDITAR', 'Se actualizó la imagen de '.$config['singular'].' «'.$registro->nombre.'».');
            $bitacora->detalle($request, $this->tipo($request), $registro->id, [$campo => $antes[$campo], 'imagen_version' => $registro->imagen_path ? basename($registro->imagen_path) : null], [$campo => $despues[$campo], 'imagen_version' => basename($ruta)]);

            return ApiResponse::make(200, 'Imagen actualizada correctamente.', $despues);
        });
    }

    public function verImagen(Request $request, Bitacora $bitacora, Imagenes $imagenes)
    {
        $config = $this->configuracion($request);
        $registro = $this->registro($request);
        $bitacora->contexto($request, $config['modulo'], 'CONSULTAR', 'Se consultó la imagen de '.$config['singular'].' «'.$registro->nombre.'».');

        return $imagenes->respuesta($registro->imagen_path);
    }

    public function orden(Request $request, Bitacora $bitacora)
    {
        $input = $request->validate(['ids' => ['required', 'array', 'list'], 'ids.*' => ['required', 'integer', 'distinct', 'exists:pilares,id']]);

        return DB::transaction(function () use ($request, $bitacora, $input) {
            $anteriores = DB::table('pilares')->orderBy('orden')->orderBy('id')->lockForUpdate()->pluck('id')->all();
            if (count($input['ids']) !== count($anteriores)) {
                throw ValidationException::withMessages(['ids' => ['Incluye todos los pilares exactamente una vez.']]);
            }
            foreach ($input['ids'] as $orden => $id) {
                DB::table('pilares')->where('id', $id)->update(['orden' => $orden + 1, 'updated_at' => now()]);
            }
            $bitacora->contexto($request, 'CATALOGO_PILARES', 'EDITAR', 'Se actualizó el orden de los pilares.');
            $bitacora->detalle($request, 'PILAR', null, ['orden_ids' => $anteriores], ['orden_ids' => array_map('intval', $input['ids'])]);

            return ApiResponse::make(200, 'Orden de pilares actualizado correctamente.', ['pilares' => DB::table('pilares')->orderBy('orden')->get()->map(fn ($fila) => $this->publico($request, $fila))]);
        });
    }

    private function tipo(Request $request): string
    {
        return match ($request->route()->defaults['catalogo']) {
            'pilares' => 'PILAR', 'marcas' => 'MARCA', 'categorias' => 'CATEGORIA',
            'tipos-beneficio' => 'TIPO_BENEFICIO', 'embotelladores' => 'EMBOTELLADOR',
        };
    }

    private function puedeConsultar(Request $request): bool
    {
        $codigo = $this->configuracion($request)['modulo'].'.CONSULTAR';

        return $request->user()->tienePermiso($codigo) && $request->user()->currentAccessToken()->can($codigo);
    }

    private function datos(Request $request, ?object $actual = null): array
    {
        $config = $this->configuracion($request);
        $reglas = [
            'nombre' => ['required', 'string', 'max:180', Rule::unique($config['tabla'], 'nombre')->ignore($actual?->id)],
            'activo' => $actual ? ['prohibited'] : ['sometimes', 'boolean'],
            'imagen_url' => ['prohibited'], 'logotipo_url' => ['prohibited'], 'imagen_path' => ['prohibited'],
        ];
        if ($config['tabla'] === 'pilares') {
            $reglas += ['descripcion' => ['nullable', 'string', 'max:10000'], 'orden' => ['sometimes', 'integer', 'min:0']];
        }
        if (in_array($config['tabla'], ['pilares', 'embotelladores'], true)) {
            $reglas['color'] = ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        if ($config['tabla'] === 'embotelladores') {
            $reglas += ['estados' => ['sometimes', 'array', 'list'], 'estados.*' => ['required', 'string', 'distinct', Rule::in(EstadosMexico::CLAVES)], 'es_nacional' => ['sometimes', 'boolean']];
        }
        $datos = $request->validate($reglas);
        unset($datos['imagen_url'], $datos['logotipo_url'], $datos['imagen_path']);
        if ($actual) {
            unset($datos['activo']);
        }
        if ($config['tabla'] === 'embotelladores') {
            if ($actual?->es_nacional) {
                abort_if($datos['nombre'] !== 'Nacional' || (isset($datos['es_nacional']) && ! $datos['es_nacional']), 409, 'La identidad de Nacional está protegida.');
                $datos['estados'] = EstadosMexico::CLAVES;
                $datos['es_nacional'] = true;
            } else {
                if ($datos['es_nacional'] ?? false) {
                    throw ValidationException::withMessages(['es_nacional' => ['Solo el registro del sistema puede ser Nacional.']]);
                }
                $datos['es_nacional'] = false;
            }
            if (isset($datos['estados']) || ! $actual) {
                $datos['estados'] = json_encode($datos['estados'] ?? [], JSON_THROW_ON_ERROR);
            }
        }

        return $datos;
    }
}
