<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function tienePermiso(string $codigo): bool
    {
        return $this->rol?->activo === true && $this->rol->permisos()
            ->join('modulos', 'modulos.id', '=', 'permisos.modulo_id')
            ->where('permisos.codigo', $codigo)
            ->where('permisos.activo', true)->where('modulos.activo', true)->exists();
    }

    public function datosPublicos(): array
    {
        return [
            'id' => $this->id, 'nombre' => $this->name,
            'apellido_paterno' => $this->apellido_paterno, 'apellido_materno' => $this->apellido_materno,
            'usuario' => $this->usuario, 'email' => $this->email,
            'activo' => $this->activo, 'bloqueado' => $this->bloqueado,
            'rol' => $this->rol?->only(['id', 'codigo', 'nombre']),
        ];
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
