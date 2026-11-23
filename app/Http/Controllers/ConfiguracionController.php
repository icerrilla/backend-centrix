<?php

namespace App\Http\Controllers;

use App\Services\Bitacora;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfiguracionController extends Controller
{
    public function index()
    {
        return ApiResponse::make(200, 'Consulta correcta.', ['visibilidad_por_embotellador' => (bool) DB::table('configuracion')->where('id', 1)->value('visibilidad_por_embotellador')]);
    }

    public function update(Request $r, Bitacora $b)
    {
        $in = $r->validate(['visibilidad_por_embotellador' => ['required', 'boolean']]);

        return DB::transaction(function () use ($r, $b, $in) {
            DB::table('configuracion')->insertOrIgnore(['id' => 1, 'visibilidad_por_embotellador' => false]);
            $antes = DB::table('configuracion')->where('id', 1)->lockForUpdate()->first();
            DB::table('configuracion')->where('id', 1)->update($in);
            $b->contexto($r, 'CONFIGURACION', 'EDITAR', 'Se '.($in['visibilidad_por_embotellador'] ? 'mostró' : 'ocultó').' el selector de embotellador.');
            $despues = ['visibilidad_por_embotellador' => (bool) $in['visibilidad_por_embotellador']];
            $b->detalle($r, 'CONFIGURACION', 1, ['visibilidad_por_embotellador' => (bool) $antes->visibilidad_por_embotellador], $despues);

            return ApiResponse::make(200, 'Configuración actualizada correctamente.', $despues);
        });
    }
}
