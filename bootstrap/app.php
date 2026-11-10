<?php

use App\Http\Middleware\AuditarSolicitudes;
use App\Http\Middleware\RequerirHttps;
use App\Http\Middleware\SesionCentrix;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AuditarSolicitudes::class);
        $middleware->append(RequerirHttps::class);
        $middleware->alias(['centrix.sesion' => SesionCentrix::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($exception instanceof ValidationException) {
                $errors = array_map(fn () => ['Revisa el valor proporcionado.'], $exception->errors());

                return ApiResponse::make(422, 'Los datos proporcionados no son válidos.', null, $errors);
            }
            if ($exception instanceof QueryException && in_array((string) $exception->getCode(), ['23000', '23503', '23505'], true)) {
                return ApiResponse::make(409, 'La operación entra en conflicto con información existente. Revisa sus relaciones o los datos registrados.');
            }
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $message = match ($status) {
                401 => 'La sesión no está disponible. Inicia sesión nuevamente.',
                403 => 'No tienes permiso para realizar esta acción.',
                404 => 'No se encontró el recurso solicitado.',
                405 => 'La operación solicitada no está disponible.',
                409 => $exception->getMessage() ?: 'La operación no se puede completar.',
                429 => 'Demasiados intentos. Intenta más tarde.',
                503 => 'El servicio no está disponible.',
                default => $status >= 500 ? 'Ocurrió un error interno del servidor.' : 'No se pudo completar la solicitud.',
            };
            $response = ApiResponse::make($status, $message);
            if ($exception instanceof HttpExceptionInterface) {
                $response->headers->add($exception->getHeaders());
            }

            return $response;
        });
    })->create();
