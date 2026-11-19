<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class Sesiones
{
    public function revocar(User $usuario): void
    {
        $usuario->tokens()->delete();
        DB::table('sessions')->where('user_id', $usuario->id)->delete();
        $usuario->forceFill(['remember_token' => null])->save();
    }
}
