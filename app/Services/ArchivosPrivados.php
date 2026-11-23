<?php

namespace App\Services;

use App\Exceptions\ValidacionNegocio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ArchivosPrivados
{
    public function guardar(Request $r, string $campo, string $carpeta, array $permitidas): array
    {
        $r->validate([$campo => ['required', 'file', 'max:20480']]);
        $file = $r->file($campo);
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
        $valido = in_array($ext, $permitidas, true) && isset($mimes[$ext]);
        if ($valido && $ext === 'pptx') {
            // No confiar solo en la extensión ni en el Content-Type del navegador.
            $zip = new ZipArchive;
            $abierto = $zip->open($file->getRealPath());
            $valido = $abierto === true && $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('ppt/presentation.xml') !== false;
            if ($abierto === true) {
                $zip->close();
            }
        } elseif ($valido) {
            $valido = $mime === $mimes[$ext];
        }
        if (! $valido) {
            throw ValidacionNegocio::campo($campo, 'El contenido y la extensión del archivo no corresponden a un formato permitido.');
        }
        $ruta = $file->storeAs($carpeta, Str::uuid().'.'.($ext === 'jpeg' ? 'jpg' : $ext), 'local');
        abort_unless($ruta, 500);
        $nuevos = $r->attributes->get('archivos_nuevos', []);
        $nuevos[] = $ruta;
        $r->attributes->set('archivos_nuevos', $nuevos);

        return ['ruta' => $ruta, 'nombre' => Str::limit(preg_replace('/[\x00-\x1f\x7f\/\\\\]/u', '_', $file->getClientOriginalName()), 250, ''), 'mime' => $mimes[$ext], 'tamano' => $file->getSize()];
    }

    public function borrar(?string $ruta): void
    {
        if ($ruta) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($ruta));
        }
    }

    public function respuesta(?string $ruta, ?string $nombre = null, bool $descarga = false, ?string $mime = null)
    {
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);
        $response = response()->file(Storage::disk('local')->path($ruta));
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($mime) {
            $response->headers->set('Content-Type', $mime);
        }
        if ($nombre) {
            $response->setContentDisposition($descarga ? 'attachment' : 'inline', $nombre, 'archivo.'.pathinfo($ruta, PATHINFO_EXTENSION));
            $disposicion = $response->headers->get('Content-Disposition');
            if (! str_contains($disposicion, 'filename*=')) {
                $response->headers->set('Content-Disposition', $disposicion."; filename*=UTF-8''".rawurlencode($nombre));
            }
        }

        return $response;
    }

    public function limpiar(Request $r): void
    {
        foreach ($r->attributes->get('archivos_nuevos', []) as $ruta) {
            $usado = false;
            foreach (['evento_imagenes' => ['archivo_path'], 'documentos' => ['archivo_path'], 'beneficios_aplicados' => ['imagen_path', 'documento_path']] as $tabla => $columnas) {
                foreach ($columnas as $columna) {
                    if (DB::table($tabla)->where($columna, $ruta)->exists()) {
                        $usado = true;
                    }
                }
            }
            if (! $usado) {
                Storage::disk('local')->delete($ruta);
            }
        }
    }
}
