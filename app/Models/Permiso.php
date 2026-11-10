<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permisos';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
