<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordBytes implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Bcrypt solo considera los primeros 72 bytes; rechazar truncamientos.
        if (is_string($value) && config('hashing.driver') === 'bcrypt' && strlen($value) > 72) {
            $fail('La contraseña supera la longitud permitida.');
        }
    }
}
