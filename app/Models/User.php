<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\CatalogoPermisos;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = ['activo' => true, 'bloqueado' => false, 'intentos_fallidos' => 0, 'requiere_establecer_password' => false];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function tienePermiso(string $codigo): bool
    {
        if (! $this->rol?->activo || ! in_array($codigo, CatalogoPermisos::codigos(), true)) {
            return false;
        }

        return $this->rol->esAdmin() || $this->rol->permisos()->disponibles()->where('permisos.codigo', $codigo)->exists();
    }

    public function datosPublicos(bool $incluirPermisos = false): array
    {
        $datos = [
            'id' => $this->id, 'nombre' => $this->name,
            'apellido_paterno' => $this->apellido_paterno, 'apellido_materno' => $this->apellido_materno,
            'usuario' => $this->usuario, 'email' => $this->email,
            'activo' => $this->activo, 'bloqueado' => $this->bloqueado,
            'rol' => $this->rol?->only(['id', 'codigo', 'nombre']),
        ];

        if ($incluirPermisos && ! $this->rol?->esAdmin()) {
            $datos['permisos'] = $this->rol?->permisosAgrupados() ?? CatalogoPermisos::agrupar([]);
        }

        return $datos;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'intentos_fallidos',
        'requiere_establecer_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'requiere_establecer_password' => 'boolean',
            'bloqueado' => 'boolean',
            'intentos_fallidos' => 'integer',
            'bloqueado_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
        ];
    }
}
