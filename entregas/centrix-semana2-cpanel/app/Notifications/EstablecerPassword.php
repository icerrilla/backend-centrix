<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EstablecerPassword extends Notification
{
    public function __construct(#[\SensitiveParameter] public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = config('centrix.password_url');
        $url = $base.(str_contains($base, '?') ? '&' : '?').http_build_query([
            'token' => $this->token, 'email' => $notifiable->email,
        ], '', '&', PHP_QUERY_RFC3986);

        return (new MailMessage)->subject('Establece tu contraseña de Centrix')
            ->greeting('Hola '.$notifiable->name)
            ->line('Utiliza este enlace para establecer o restablecer tu contraseña.')
            ->action('Establecer contraseña', $url)
            ->line('El enlace vence en '.config('auth.passwords.users.expire').' minutos y solo puede utilizarse una vez.')
            ->line('Si no solicitaste este acceso, puedes ignorar el mensaje.');
    }
}
