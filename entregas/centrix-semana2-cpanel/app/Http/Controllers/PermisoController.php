<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Services\Bitacora;
use App\Support\ApiResponse;
use App\Support\CatalogoPermisos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermisoController extends Controller
{
    public function index(Request $request, Bitacora $bitacora)
    {
        $input = $request->validate(['modulo' => ['sometimes', 'string', Rule::in(array_keys(CatalogoPermisos::modulos()))]]);
        $disponibles = Permiso::disponibles()->pluck('permisos.codigo')->all();
        $grupos = [];
        foreach (CatalogoPermisos::agrupar($disponibles) as $grupo) {
            $codigo = $grupo['modulo'];
            if (isset($input['modulo']) && $input['modulo'] !== $codigo) {
                continue;
            }
            $grupos[] = ['modulo' => $codigo, 'nombre' => CatalogoPermisos::modulos()[$codigo][0], 'permisos' => $grupo['permisos']];
        }
        $bitacora->contexto($request, 'PERMISOS', 'CONSULTAR', 'Se consultó el catálogo de permisos por módulo.');

        return ApiResponse::make(200, 'Consulta correcta.', ['permisos' => $grupos]);
    }
}
