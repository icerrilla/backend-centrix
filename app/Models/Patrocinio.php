<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patrocinio extends Model
{
    protected $table = 'patrocinios';

    protected $attributes = ['estado' => 'BORRADOR'];

    protected function casts(): array
    {
        return ['vigencia_inicio' => 'date', 'vigencia_fin' => 'date', 'inversion' => 'decimal:2', 'audiencia_promedio' => 'decimal:2'];
    }

    public function pilar(): BelongsTo
    {
        return $this->belongsTo(Pilar::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function ambito(): BelongsTo
    {
        return $this->belongsTo(Embotellador::class, 'ambito_id');
    }

    public function marcas(): BelongsToMany
    {
        return $this->belongsToMany(Marca::class, 'patrocinio_marca', 'patrocinio_id', 'marca_id')->orderBy('marcas.id');
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(PatrocinioContacto::class)->orderBy('orden')->orderBy('id');
    }

    public function scopeVisiblesPara(Builder $query, User $usuario): Builder
    {
        if (! $usuario->tienePermiso('PATROCINIOS.CONSULTAR') || ! ($usuario->currentAccessToken()?->can('PATROCINIOS.CONSULTAR') ?? true)) {
            $query->where('estado', 'PUBLICADO')->whereIn('ambito_id', $usuario->ambitoIdsVisibles());
        }

        return $query;
    }

    public function datosPublicos(): array
    {
        return $this->only(['id', 'nombre', 'descripcion', 'pilar_id', 'categoria_id', 'ambito_id', 'contraparte', 'alcance', 'ciudad_territorio', 'responsable', 'estado']) + [
            'vigencia_inicio' => $this->vigencia_inicio?->format('Y-m-d'), 'vigencia_fin' => $this->vigencia_fin?->format('Y-m-d'),
            'inversion' => $this->inversion === null ? null : (float) $this->inversion,
            'audiencia_promedio' => $this->audiencia_promedio === null ? null : (float) $this->audiencia_promedio,
            'pilar' => $this->pilar?->only(['id', 'nombre']), 'categoria' => $this->categoria?->only(['id', 'nombre']),
            'ambito' => $this->ambito?->only(['id', 'nombre']),
            'marcas' => $this->marcas->map->only(['id', 'nombre'])->values()->all(),
            'marca_ids' => $this->marcas->modelKeys(),
            'contactos' => $this->contactos->map->only(['nombre', 'email', 'rol'])->values()->all(),
            'imagen_url' => $this->imagen_path ? route('patrocinios.imagen.ver', ['id' => $this->id]) : null,
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
