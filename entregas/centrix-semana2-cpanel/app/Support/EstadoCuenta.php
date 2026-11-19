<?php

namespace App\Support;

use Illuminate\Http\Request;

class EstadoCuenta
{
    public static function validar(Request $request): bool
    {
        // También admite los textos solicitados sin depender de su valor booleano en PHP.
        $valor = $request->input('activo');
        if (is_string($valor)) {
            $normalizado = strtolower(trim($valor));
            if (in_array($normalizado, ['activo', 'inactivo'], true)) {
                $request->merge(['activo' => $normalizado === 'activo']);
            }
        }
        $datos = $request->validate(['activo' => ['required', 'boolean']]);

        return (bool) $datos['activo'];
    }
}
