<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

class ValidacionPublicacion extends ValidationException
{
    public string $mensajePublico = '';

    public static function faltantes(array $campos): self
    {
        $exception = self::withMessages(array_map(fn ($nombre) => ['El campo '.$nombre.' es obligatorio para publicar.'], $campos));
        $exception->mensajePublico = 'Para publicar falta: '.implode(', ', array_values($campos)).'.';

        return $exception;
    }
}
