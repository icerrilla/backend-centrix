<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatrocinioContacto extends Model
{
    protected $table = 'patrocinio_contactos';

    public $timestamps = false;

    protected $fillable = ['nombre', 'email', 'rol', 'orden'];
}
