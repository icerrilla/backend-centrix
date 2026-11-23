<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

class ValidacionNegocio extends ValidationException
{
    public string $mensajePublico;

    public static function campo(string $campo, string $mensaje): self
    {
        $exception = self::withMessages([$campo => [$mensaje]]);
        $exception->mensajePublico = $mensaje;

        return $exception;
    }
}
