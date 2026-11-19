<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Embotellador extends Model
{
    protected $table = 'embotelladores';

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'es_nacional' => 'boolean', 'estados' => 'array'];
    }

    public static function nacional(): self
    {
        return self::where('clave_sistema', 'NACIONAL')->where('es_nacional', true)->where('activo', true)->firstOrFail();
    }
}
