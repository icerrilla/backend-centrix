<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Imagenes
{
    public function guardar(Request $request, string $carpeta, ?string $anterior): string
    {
        $request->validate(['archivo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480']]);
        $archivo = $request->file('archivo');
        $extension = match ($archivo->getMimeType()) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => abort(422),
        };
        $ruta = $archivo->storeAs($carpeta, Str::uuid().'.'.$extension, 'local');
        abort_unless($ruta, 500);
        $nuevos = $request->attributes->get('imagenes_nuevas', []);
        $nuevos[] = $ruta;
        $request->attributes->set('imagenes_nuevas', $nuevos);
        if ($anterior) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($anterior));
        }

        return $ruta;
    }

    public function respuesta(?string $ruta, bool $privada = false)
    {
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);
        $respuesta = response()->file(Storage::disk('local')->path($ruta), [
            'Cache-Control' => $privada ? 'private, no-store' : 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        if ($privada) {
            $respuesta->setPrivate();
            $respuesta->headers->set('Cache-Control', 'private, no-store');
        }

        return $respuesta;
    }

    public function limpiarFallidos(Request $request): void
    {
        foreach ($request->attributes->get('imagenes_nuevas', []) as $ruta) {
            $usada = false;
            foreach (['pilares', 'marcas', 'patrocinios'] as $tabla) {
                if (DB::table($tabla)->where('imagen_path', $ruta)->exists()) {
                    $usada = true;
                    break;
                }
            }
            if (! $usada) {
                Storage::disk('local')->delete($ruta);
            }
        }
    }
}
