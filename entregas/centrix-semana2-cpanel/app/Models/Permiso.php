<?php

namespace App\Models;

use App\Support\CatalogoPermisos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permisos';

    public function scopeDelCatalogo(Builder $query): Builder
    {
        return $query->whereIn('permisos.codigo', CatalogoPermisos::codigos());
    }

    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->delCatalogo()->where('permisos.activo', true)
            ->whereExists(function ($subquery) {
                $subquery->selectRaw('1')->from('modulos')
                    ->whereColumn('modulos.id', 'permisos.modulo_id')->where('modulos.activo', true);
            });
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
