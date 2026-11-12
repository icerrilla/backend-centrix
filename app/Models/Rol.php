<?php

namespace App\Models;

use App\Support\CatalogoPermisos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $attributes = ['activo' => true];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'rol_id');
    }

    public function esAdmin(): bool
    {
        return $this->codigo === 'ADMIN';
    }

    public function permisosAgrupados(): array
    {
        return CatalogoPermisos::agrupar($this->esAdmin() && $this->activo
            ? CatalogoPermisos::codigos() : $this->codigosPermisosEfectivos());
    }

    public function datosPublicos(): array
    {
        return [
            'id' => $this->id, 'codigo' => $this->codigo, 'nombre' => $this->nombre,
            'activo' => $this->activo, 'usuarios_asignados' => $this->usuarios_count ?? $this->usuarios()->count(),
            'permisos' => $this->permisosAgrupados(),
        ];
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'rol_id', 'permiso_id');
    }

    public function codigosPermisosEfectivos(): array
    {
        if (! $this->activo) {
            return [];
        }

        return $this->permisos()
            ->disponibles()
            ->orderBy('permisos.codigo')
            ->pluck('permisos.codigo')->all();
    }
}
