<?php

namespace App\Http\Middleware;

use App\Services\Bitacora;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditarSolicitudes
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/login', 'api/forgot-password', 'api/reset-password')) {
            app(Bitacora::class)->contexto($request, 'AUTENTICACION', match (true) {
                $request->is('api/login') => 'LOGIN',
                $request->is('api/forgot-password') => 'RECUPERAR_ACCESO',
                default => 'ESTABLECER_PASSWORD',
            }, 'No se pudo completar la solicitud de acceso.');
            $login = $request->input('login', $request->input('email'));
            if (is_string($login) && mb_strlen($login) <= 255) {
                $request->attributes->set('login_auditoria', $login);
            }
        }
        // El sitio inicial sigue disponible antes de instalar el esquema.
        try {
            $lista = Schema::hasTable('bitacora');
        } catch (Throwable) {
            $lista = false;
        }
        if (! $lista && $request->is('api/*')) {
            return ApiResponse::make(503, 'El servicio no está disponible.');
        }
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $handler = app(ExceptionHandler::class);
            $handler->report($exception);
            $response = $handler->render($request, $exception);
        }
        if ($lista) {
            app(Bitacora::class)->registrar($request, $response->getStatusCode());
        }

        return $response;
    }
}
