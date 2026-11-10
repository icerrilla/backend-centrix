<?php

namespace App\Http\Middleware;

use App\Models\Rol;
use App\Models\User;
use App\Services\Bitacora;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class SesionCentrix
{
    public function handle(Request $request, Closure $next, ?string $permiso = null)
    {
        if ($permiso) {
            [$modulo, $accion] = explode('.', $permiso, 2);
            app(Bitacora::class)->contexto($request, $modulo, $accion, 'No se pudo completar la solicitud.');
        }
        $encontrado = PersonalAccessToken::findToken($request->bearerToken() ?? '');
        if (! $encontrado || $encontrado->tokenable_type !== (new User)->getMorphClass()) {
            return ApiResponse::make(401, 'La sesión no está disponible. Inicia sesión nuevamente.');
        }

        // El mismo orden usuario -> token serializa login, revocación y peticiones.
        // Se conserva el bloqueo hasta terminar la operación y su bitácora.
        return DB::transaction(function () use ($request, $next, $permiso, $encontrado) {
            // Serializa administración de cuentas antes de bloquear actor y destino;
            // evita que dos administradores se retiren mutuamente el último acceso.
            if ($request->is('api/usuarios*') && ! $request->isMethodSafe()) {
                Rol::where('codigo', 'ADMIN')->lockForUpdate()->first();
            }
            $usuario = User::query()->lockForUpdate()->find($encontrado->tokenable_id);
            $token = PersonalAccessToken::query()->lockForUpdate()->find($encontrado->id);
            if (! $usuario || ! $token) {
                return ApiResponse::make(401, 'La sesión no está disponible. Inicia sesión nuevamente.');
            }
            $request->setUserResolver(fn () => $usuario->withAccessToken($token));
            $ultimaActividad = $token->last_used_at ?? $token->created_at;
            if ($ultimaActividad->lte(now()->subSeconds(config('centrix.inactividad_segundos')))
                || ($token->expires_at && $token->expires_at->lte(now()))) {
                $token->delete();

                return ApiResponse::make(401, 'La sesión expiró por inactividad. Inicia sesión nuevamente.');
            }
            if (! $usuario->activo || $usuario->bloqueado || ! $usuario->rol?->activo || $usuario->requiere_establecer_password) {
                $usuario->tokens()->delete();

                return ApiResponse::make(403, 'La cuenta no está disponible.');
            }
            if ($permiso && (! $usuario->tienePermiso($permiso) || ! $token->can($permiso))) {
                return ApiResponse::make(403, 'No tienes permiso para realizar esta acción.');
            }
            $response = $next($request);
            if ($response->getStatusCode() < 400 && $token->exists) {
                // No reinsertar tokens eliminados durante logout o cambios de acceso.
                PersonalAccessToken::whereKey($token->id)->update(['last_used_at' => now()]);
            }
            app(Bitacora::class)->registrar($request, $response->getStatusCode());

            return $response;
        });
    }
}
