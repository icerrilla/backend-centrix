<?php

namespace App\Http\Controllers;

use App\Services\Bitacora;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogoAccesoController extends Controller
{
    public function bitacora(Request $request, Bitacora $bitacora)
    {
        $input = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'],
            'modulo' => ['sometimes', 'string', 'max:50'], 'accion' => ['sometimes', 'string', 'max:50'],
            'resultado' => ['sometimes', 'string', 'max:50'],
            'usuario_id' => ['sometimes', 'integer', 'min:1'],
            'registro_tipo' => ['sometimes', 'string', 'max:60'], 'registro_id' => ['sometimes', 'integer', 'min:1'],
            'fecha_desde' => ['sometimes', 'date_format:Y-m-d'],
            'fecha_hasta' => ['sometimes', 'date_format:Y-m-d', Rule::when($request->filled('fecha_desde'), 'after_or_equal:fecha_desde')],
        ]);
        $query = DB::table('bitacora')->orderByDesc('id');
        foreach (['modulo', 'accion', 'resultado', 'usuario_id', 'registro_tipo', 'registro_id'] as $campo) {
            if (isset($input[$campo])) {
                $query->where($campo, $input[$campo]);
            }
        }
        if (isset($input['fecha_desde'])) {
            $query->where('created_at', '>=', $input['fecha_desde'].' 00:00:00');
        }
        if (isset($input['fecha_hasta'])) {
            $query->where('created_at', '<=', $input['fecha_hasta'].' 23:59:59');
        }
        $pagina = $query->paginate($input['per_page'] ?? 20);
        $registros = collect($pagina->items())->map(function ($registro) {
            $registro->cambios = $registro->cambios ? json_decode($registro->cambios, true) : [];

            return $registro;
        });
        $bitacora->contexto($request, 'BITACORA', 'CONSULTAR', 'Se consultó la bitácora de actividades.');

        return ApiResponse::make(200, 'Consulta correcta.', [
            'registros' => $registros,
            'paginacion' => ['pagina' => $pagina->currentPage(), 'por_pagina' => $pagina->perPage(), 'total' => $pagina->total(), 'ultima_pagina' => $pagina->lastPage()],
        ]);
    }
}
