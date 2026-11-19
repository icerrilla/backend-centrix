<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class RequerirHttps
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*') && config('centrix.https_obligatorio') && ! $request->isSecure()) {
            return ApiResponse::make(400, 'Utiliza una conexión HTTPS segura.');
        }

        return $next($request);
    }
}
