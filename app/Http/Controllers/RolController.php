<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Services\Bitacora;
use App\Services\Sesiones;
use App\Support\ApiResponse;
use App\Support\CatalogoPermisos;
use App\Support\EstadoCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RolController extends Controller
{
    public function index(Request $request, Bitacora $bitacora)
    {
        $input = $request->validate([
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'], 'activo' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Rol::withCount('usuarios')->orderBy('id');
        if (! empty($input['buscar'])) {
            $query->where(function ($query) use ($input) {
                $query->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($input['buscar']).'%'])
                    ->orWhereRaw('LOWER(codigo) LIKE ?', ['%'.mb_strtolower($input['buscar']).'%']);
            });
        }
        if (array_key_exists('activo', $input)) {
            $query->where('activo', $input['activo']);
        }
        $pagina = $query->paginate($input['per_page'] ?? 20);
        $bitacora->contexto($request, 'ROLES', 'CONSULTAR', 'Se consultó el catálogo de roles.');

        return ApiResponse::make(200, 'Consulta correcta.', [
            'roles' => $pagina->getCollection()->map->datosPublicos()->values(),
            'paginacion' => ['pagina' => $pagina->currentPage(), 'por_pagina' => $pagina->perPage(), 'total' => $pagina->total(), 'ultima_pagina' => $pagina->lastPage()],
        ]);
    }

    public function show(Request $request, int $id, Bitacora $bitacora)
    {
        $rol = Rol::withCount('usuarios')->findOrFail($id);
        $bitacora->contexto($request, 'ROLES', 'CONSULTAR', 'Se consultó el rol '.$rol->nombre.'.');

        return ApiResponse::make(200, 'Consulta correcta.', $rol->datosPublicos());
    }

    public function store(Request $request, Bitacora $bitacora)
    {
        $datos = $this->datos($request);

        return DB::transaction(function () use ($request, $datos, $bitacora) {
            $rol = new Rol;
            $rol->forceFill($datos)->save();
            $bitacora->contexto($request, 'ROLES', 'CREAR', 'Se creó el rol '.$rol->nombre.'.');

            return ApiResponse::make(201, 'Rol creado correctamente.', $rol->datosPublicos());
        });
    }

    public function update(Request $request, int $id, Bitacora $bitacora)
    {
        return DB::transaction(function () use ($request, $id, $bitacora) {
            $rol = Rol::lockForUpdate()->findOrFail($id);
            $this->editable($rol);
            $datos = $this->datos($request, $rol);
            $anterior = $rol->nombre;
            $codigoAnterior = $rol->codigo;
            $rol->forceFill($datos)->save();
            $bitacora->contexto($request, 'ROLES', 'EDITAR', 'Se actualizó el rol de «'.$anterior.'» ('.$codigoAnterior.') a «'.$rol->nombre.'» ('.$rol->codigo.').');

            return ApiResponse::make(200, 'Rol actualizado correctamente.', $rol->datosPublicos());
        });
    }

    public function estado(Request $request, int $id, Bitacora $bitacora, Sesiones $sesiones)
    {
        $activo = EstadoCuenta::validar($request);

        return DB::transaction(function () use ($request, $id, $bitacora, $sesiones, $activo) {
            $rol = Rol::lockForUpdate()->findOrFail($id);
            $this->editable($rol);
            $rol->activo = $activo;
            $rol->save();
            if (! $activo) {
                foreach ($rol->usuarios()->orderBy('id')->lockForUpdate()->get() as $usuario) {
                    $sesiones->revocar($usuario);
                    Password::deleteToken($usuario);
                }
            }
            $bitacora->contexto($request, 'ROLES', $activo ? 'ACTIVAR' : 'INACTIVAR', 'Se '.($activo ? 'activó' : 'inactivó').' el rol '.$rol->nombre.'.');

            return ApiResponse::make(200, 'Estado del rol actualizado correctamente.', $rol->datosPublicos());
        });
    }

    public function asignarPermisos(Request $request, int $id, Bitacora $bitacora)
    {
        $input = $request->validate([
            'permisos' => ['present', 'array', 'list'],
            'permisos.*' => ['array:modulo,permisos'],
            'permisos.*.modulo' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(CatalogoPermisos::modulos()))],
            'permisos.*.permisos' => ['present', 'array'],
            'permisos.*.permisos.*' => ['boolean'],
        ]);
        $codigos = [];
        foreach ($input['permisos'] as $indice => $grupo) {
            $acciones = CatalogoPermisos::modulos()[$grupo['modulo']][1];
            foreach ($grupo['permisos'] as $accion => $concedido) {
                if (! is_bool($concedido) || ! in_array($accion, array_map('strtolower', $acciones), true)) {
                    throw ValidationException::withMessages(['permisos.'.$indice.'.permisos' => ['Usa acciones del catálogo con valores true o false.']]);
                }
                if ($concedido) {
                    $codigos[] = $grupo['modulo'].'.'.strtoupper($accion);
                }
            }
        }

        return DB::transaction(function () use ($request, $id, $bitacora, $codigos) {
            $rol = Rol::lockForUpdate()->findOrFail($id);
            $this->editable($rol);
            $permisos = Permiso::disponibles()->whereIn('permisos.codigo', $codigos)->lockForUpdate()->get();
            if ($permisos->count() !== count($codigos)) {
                throw ValidationException::withMessages(['permisos' => ['Hay permisos que no están disponibles.']]);
            }
            $anteriores = $rol->codigosPermisosEfectivos();
            $rol->permisos()->sync($permisos->modelKeys());
            $nuevos = $rol->codigosPermisosEfectivos();
            $descripcion = 'Se actualizaron los permisos del rol '.$rol->nombre.'.';
            $agregados = array_diff($nuevos, $anteriores);
            $retirados = array_diff($anteriores, $nuevos);
            if ($agregados) {
                $descripcion .= ' Se agregaron: '.implode(', ', array_map(CatalogoPermisos::descripcion(...), $agregados)).'.';
            }
            if ($retirados) {
                $descripcion .= ' Se retiraron: '.implode(', ', array_map(CatalogoPermisos::descripcion(...), $retirados)).'.';
            }
            $bitacora->contexto($request, 'ROLES', 'ASIGNAR_PERMISOS', $descripcion);

            return ApiResponse::make(200, 'Permisos asignados correctamente.', $rol->datosPublicos());
        });
    }

    public function destroy(Request $request, int $id, Bitacora $bitacora)
    {
        $request->validate(['confirmar' => ['required', 'accepted']]);

        return DB::transaction(function () use ($request, $id, $bitacora) {
            $rol = Rol::lockForUpdate()->findOrFail($id);
            $this->editable($rol);
            abort_if($rol->usuarios()->exists(), 409, 'El rol tiene usuarios asignados y no se puede eliminar.');
            $nombre = $rol->nombre;
            $rol->delete();
            $bitacora->contexto($request, 'ROLES', 'ELIMINAR', 'Se eliminó el rol '.$nombre.'.');

            return ApiResponse::make(200, 'Rol eliminado correctamente.');
        });
    }

    private function editable(Rol $rol): void
    {
        abort_if($rol->esAdmin(), 409, 'El rol ADMIN está protegido y no se puede modificar ni eliminar.');
    }

    private function datos(Request $request, ?Rol $rol = null): array
    {
        if (is_string($request->input('codigo'))) {
            $request->merge(['codigo' => strtoupper(trim($request->input('codigo')))]);
        }

        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::notIn(['ADMIN']), Rule::unique('roles', 'codigo')->ignore($rol?->id)],
            'nombre' => ['required', 'string', 'max:255'],
            'activo' => ['prohibited'], 'permisos' => ['prohibited'], 'permiso_ids' => ['prohibited'],
        ]);

        return ['codigo' => $datos['codigo'], 'nombre' => $datos['nombre']];
    }
}
