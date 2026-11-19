<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\EstablecerPassword;
use Illuminate\Support\Facades\Password;

class EnlacesPassword
{
    public function enviar(User $usuario): string
    {
        $this->validarConfiguracion();

        return Password::sendResetLink(['email' => $usuario->email], function ($user, $token) {
            $user->notify(new EstablecerPassword($token));
        });
    }

    public function validarConfiguracion(): void
    {
        $url = config('centrix.password_url');
        abort_unless(is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)
            && (! config('centrix.https_obligatorio') || parse_url($url, PHP_URL_SCHEME) === 'https'), 503);
        $this->validarMailer(config('mail.default'));

    }

    private function validarMailer(string $nombre, array $visitados = []): void
    {
        abort_if(in_array($nombre, $visitados, true), 503);
        $config = config('mail.mailers.'.$nombre, []);
        abort_unless(is_array($config) && isset($config['transport']), 503);
        // Un enlace de acceso nunca se escribe en archivos de log.
        abort_if(($config['transport'] ?? null) === 'log', 503);
        abort_if(($config['transport'] ?? null) === 'array' && ! app()->environment('testing'), 503);
        foreach ($config['mailers'] ?? [] as $hijo) {
            $this->validarMailer($hijo, [...$visitados, $nombre]);
        }
    }
}
