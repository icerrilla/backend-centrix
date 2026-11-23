<?php

namespace App\Services;

use App\Exceptions\ValidacionNegocio;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccesoNegocio
{
    public function puede(Request $r, string $codigo): bool
    {
        return $r->user()->tienePermiso($codigo) && $r->user()->currentAccessToken()->can($codigo);
    }

    public function patrocinio(Builder $q, string $columna, User $u): void
    {
        $q->whereIn($columna, DB::table('patrocinios')->select('id')->where('estado', 'PUBLICADO')->whereIn('ambito_id', $u->ambitoIdsVisibles()));
    }

    public function eventos(Request $r): Builder
    {
        $q = DB::table('eventos');
        if (! $this->puede($r, 'EVENTOS.CONSULTAR')) {
            $q->whereIn('patrocinio_id', DB::table('patrocinios')->select('id')->where('estado', 'PUBLICADO'))
                ->whereIn('ambito_id', $r->user()->ambitoIdsVisibles());
        }

        return $q;
    }

    public function query(Request $r, string $tabla, string $modulo, bool $ocultarInactivos = true): Builder
    {
        if ($tabla === 'eventos') {
            return $this->eventos($r);
        }
        $q = DB::table($tabla);
        if (! $this->puede($r, $modulo.'.CONSULTAR')) {
            $this->patrocinio($q, 'patrocinio_id', $r->user());
            if ($ocultarInactivos && in_array($tabla, ['hitos', 'beneficios'], true)) {
                $q->where('activo', true);
            }
            if ($tabla === 'documentos') {
                $q->where('estado', 'ACTIVO');
                $this->eventoAsociadoVisible($q, $r);
            }
        }

        return $q;
    }

    public function eventoAsociadoVisible(Builder $q, Request $r): void
    {
        // El permiso CONSULTAR del módulo hijo ya se comprobó; no heredar EVENTOS.CONSULTAR.
        $q->where(function ($q) use ($r) {
            $q->whereNull('evento_id')->orWhereIn('evento_id', DB::table('eventos')->select('id')
                ->whereIn('patrocinio_id', DB::table('patrocinios')->select('id')->where('estado', 'PUBLICADO'))
                ->whereIn('ambito_id', $r->user()->ambitoIdsVisibles()));
        });
    }

    public function relacionEvento(Request $r, ?int $id, string $modulo, ?int $patrocinio = null, bool $validarAmbito = true): void
    {
        if (! $id) {
            return;
        }
        $evento = DB::table('eventos')->where('id', $id)->lockForUpdate()->firstOrFail();
        if ($patrocinio !== null && $evento->patrocinio_id !== $patrocinio) {
            throw ValidacionNegocio::campo('evento_id', 'El evento debe pertenecer al patrocinio seleccionado.');
        }
        if ($validarAmbito && ! $this->puede($r, $modulo.'.CONSULTAR')) {
            abort_unless(in_array($evento->ambito_id, $r->user()->ambitoIdsVisibles()), 404);
        }
    }

    public function resumen(?int $id, string $tabla, array $campos = ['id', 'nombre']): ?object
    {
        return $id ? DB::table($tabla)->where('id', $id)->first($campos) : null;
    }

    public function pagina($pagina, callable $map, string $clave): array
    {
        return [$clave => $pagina->getCollection()->map($map)->values(), 'paginacion' => [
            'pagina' => $pagina->currentPage(), 'por_pagina' => $pagina->perPage(), 'total' => $pagina->total(), 'ultima_pagina' => $pagina->lastPage(),
        ]];
    }

    public function fechaUTC(string $fecha): string
    {
        return Carbon::parse($fecha)->utc()->format('Y-m-d H:i:s');
    }

    public function reglaInstante(): array
    {
        return ['date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/'];
    }
}
