<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        $consumos = DB::table('beneficios_aplicados')->select('beneficio_id')->selectRaw('SUM(cantidad) as usados')->groupBy('beneficio_id');
        $beneficios = DB::table('beneficios')->leftJoinSub($consumos, 'consumos', fn ($join) => $join->on('consumos.beneficio_id', '=', 'beneficios.id'))
            ->where('beneficios.patrocinio_id', $this->id)->where('beneficios.activo', true)
            ->get(['beneficios.disponibles', DB::raw('COALESCE(consumos.usados, 0) as usados')]);
        $avances = $beneficios->map(fn ($b) => ['agotado' => (int) $b->usados === (int) $b->disponibles, 'avance' => $b->usados / $b->disponibles * 100]);

        return $this->only(['id', 'nombre', 'descripcion', 'pilar_id', 'categoria_id', 'ambito_id', 'contraparte', 'alcance', 'ciudad_territorio', 'responsable', 'estado']) + [
            'beneficios_total' => $beneficios->count(), 'beneficios_agotados' => $avances->where('agotado', true)->count(), 'avance_beneficios' => round($avances->avg('avance') ?? 0, 2),
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
