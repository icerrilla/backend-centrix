<?php

namespace App\Http\Controllers;

use App\Services\Bitacora;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogoAccesoController extends Controller
{
    public function bitacora(Request $request, Bitacora $bitacora)
    {
        $input = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'],
            'modulo' => ['sometimes', 'string', 'max:50'], 'accion' => ['sometimes', 'string', 'max:50'],
            'resultado' => ['sometimes', 'string', 'max:50'],
        ]);
        $query = DB::table('bitacora')->orderByDesc('id');
        foreach (['modulo', 'accion', 'resultado'] as $campo) {
            if (isset($input[$campo])) {
                $query->where($campo, $input[$campo]);
            }
        }
        $pagina = $query->paginate($input['per_page'] ?? 20);
        $bitacora->contexto($request, 'BITACORA', 'CONSULTAR', 'Se consultó la bitácora de actividades.');

        return ApiResponse::make(200, 'Consulta correcta.', [
            'registros' => $pagina->items(),
            'paginacion' => ['pagina' => $pagina->currentPage(), 'por_pagina' => $pagina->perPage(), 'total' => $pagina->total(), 'ultima_pagina' => $pagina->lastPage()],
        ]);
    }
}
