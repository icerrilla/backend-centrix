<?php

namespace App\Http\Controllers;

use App\Services\AccesoNegocio;
use App\Services\ArchivosPrivados;
use App\Services\Bitacora;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocumentoController extends Controller
{
    public function __construct(private AccesoNegocio $acceso) {}

    public function publico(object $d): array
    {
        return collect((array) $d)->except(['archivo_path', 'autor_id', 'patrocinio_id', 'evento_id'])->all() + [
            'patrocinio' => $this->acceso->resumen($d->patrocinio_id, 'patrocinios'), 'evento' => $this->acceso->resumen($d->evento_id, 'eventos'),
            'autor' => $d->autor_id ? DB::table('users')->where('id', $d->autor_id)->first(['id', 'name as nombre']) : null,
        ];
    }

    public function index(Request $r)
    {
        $in = $r->validate(['patrocinio_id' => ['sometimes', 'integer', 'min:1'], 'evento_id' => ['sometimes', 'integer', 'min:1'], 'categoria' => ['sometimes', Rule::in(['CONTENIDO_VISUAL', 'REPORTE_POST_EVENTO', 'OTRO'])], 'estado' => ['sometimes', Rule::in(['ACTIVO', 'RETIRADO'])], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        abort_if(($in['estado'] ?? '') === 'RETIRADO' && ! $this->acceso->puede($r, 'DOCUMENTOS.CONSULTAR'), 403);
        $q = $this->acceso->query($r, 'documentos', 'DOCUMENTOS');
        foreach (['patrocinio_id', 'evento_id', 'categoria', 'estado'] as $c) {
            if (isset($in[$c])) {
                $q->where($c, $in[$c]);
            }
        }

        return ApiResponse::make(200, 'Consulta correcta.', $this->acceso->pagina($q->orderByDesc('id')->paginate($in['per_page'] ?? 20), fn ($d) => $this->publico($d), 'documentos'));
    }

    private function datos(Request $r): array
    {
        $in = $r->validate(['nombre' => ['required', 'string', 'max:255'], 'descripcion' => ['nullable', 'string', 'max:20000'], 'categoria' => ['required', Rule::in(['CONTENIDO_VISUAL', 'REPORTE_POST_EVENTO', 'OTRO'])],
            'patrocinio_id' => ['required', 'integer', 'exists:patrocinios,id'], 'evento_id' => ['nullable', 'integer', Rule::exists('eventos', 'id')->where('patrocinio_id', $r->input('patrocinio_id'))], 'estado' => ['prohibited']]);
        $in += ['evento_id' => null, 'descripcion' => null];
        if (! $this->acceso->puede($r, 'DOCUMENTOS.CONSULTAR')) {
            $q = DB::table('patrocinios');
            $this->acceso->patrocinio($q, 'id', $r->user());
            $q->where('id', $in['patrocinio_id'])->firstOrFail();
        }
        $this->acceso->relacionEvento($r, $in['evento_id'], 'DOCUMENTOS', (int) $in['patrocinio_id']);

        return $in;
    }

    public function store(Request $r, ArchivosPrivados $a, Bitacora $b)
    {
        return DB::transaction(function () use ($r, $a, $b) {
            $datos = $this->datos($r);
            $file = $a->guardar($r, 'archivo', 'documentos', ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'pptx']);
            $id = DB::table('documentos')->insertGetId($datos + ['autor_id' => $r->user()->id, 'archivo_path' => $file['ruta'], 'nombre_archivo' => $file['nombre'], 'tipo_mime' => $file['mime'], 'tamano' => $file['tamano'], 'created_at' => now(), 'updated_at' => now()]);
            $publico = $this->publico(DB::table('documentos')->find($id));
            $b->contexto($r, 'DOCUMENTOS', 'SUBIR', 'Se subió el documento «'.$datos['nombre'].'».');
            $b->detalle($r, 'DOCUMENTO', $id, [], $publico);

            return ApiResponse::make(201, 'Documento subido correctamente.', $publico);
        });
    }

    public function update(Request $r, int $id, Bitacora $b)
    {
        $r->validate(['archivo' => ['prohibited']]);

        return DB::transaction(function () use ($r, $id, $b) {
            $d = $this->acceso->query($r, 'documentos', 'DOCUMENTOS')->where('id', $id)->lockForUpdate()->firstOrFail();
            $datos = $this->datos($r);
            DB::table('documentos')->where('id', $id)->update($datos + ['updated_at' => now()]);
            $publico = $this->publico(DB::table('documentos')->find($id));
            $b->contexto($r, 'DOCUMENTOS', 'EDITAR', 'Se actualizó el documento «'.$datos['nombre'].'».');
            $b->detalle($r, 'DOCUMENTO', $id, $this->publico($d), $publico);

            return ApiResponse::make(200, 'Documento actualizado correctamente.', $publico);
        });
    }

    public function reemplazar(Request $r, int $id, ArchivosPrivados $a, Bitacora $b)
    {
        return DB::transaction(function () use ($r, $id, $a, $b) {
            $d = $this->acceso->query($r, 'documentos', 'DOCUMENTOS')->where('id', $id)->lockForUpdate()->firstOrFail();
            $file = $a->guardar($r, 'archivo', 'documentos', ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'pptx']);
            DB::table('documentos')->where('id', $id)->update(['archivo_path' => $file['ruta'], 'nombre_archivo' => $file['nombre'], 'tipo_mime' => $file['mime'], 'tamano' => $file['tamano'], 'updated_at' => now()]);
            $a->borrar($d->archivo_path);
            $publico = $this->publico(DB::table('documentos')->find($id));
            $b->contexto($r, 'DOCUMENTOS', 'EDITAR', 'Se reemplazó el archivo del documento «'.$d->nombre.'».');
            $b->detalle($r, 'DOCUMENTO', $id, $this->publico($d) + ['archivo_version' => basename($d->archivo_path)], $publico + ['archivo_version' => basename($file['ruta'])]);

            return ApiResponse::make(200, 'Archivo reemplazado correctamente.', $publico);
        });
    }

    public function estado(Request $r, int $id, Bitacora $b)
    {
        $in = $r->validate(['estado' => ['required', Rule::in(['ACTIVO', 'RETIRADO'])]]);

        return DB::transaction(function () use ($r, $id, $b, $in) {
            $q = DB::table('documentos');
            if (! $this->acceso->puede($r, 'DOCUMENTOS.CONSULTAR')) {
                $this->acceso->patrocinio($q, 'patrocinio_id', $r->user());
                $this->acceso->eventoAsociadoVisible($q, $r);
            }
            $d = $q->where('id', $id)->lockForUpdate()->firstOrFail();
            DB::table('documentos')->where('id', $id)->update($in + ['updated_at' => now()]);
            $b->contexto($r, 'DOCUMENTOS', $in['estado'] === 'ACTIVO' ? 'RESTAURAR' : 'RETIRAR', 'Se cambió el documento «'.$d->nombre.'» a '.$in['estado'].'.');
            $b->detalle($r, 'DOCUMENTO', $id, ['estado' => $d->estado], $in);

            return ApiResponse::make(200, 'Estado del documento actualizado correctamente.', $this->publico(DB::table('documentos')->find($id)));
        });
    }

    public function archivo(Request $r, int $id, ArchivosPrivados $a, Bitacora $b)
    {
        $r->validate(['descargar' => ['sometimes', 'boolean']]);
        $d = $this->acceso->query($r, 'documentos', 'DOCUMENTOS')->where('id', $id)->firstOrFail();
        if ($r->boolean('descargar')) {
            abort_unless($this->acceso->puede($r, 'DOCUMENTOS.DESCARGAR'), 403);
        }
        $b->contexto($r, 'DOCUMENTOS', $r->boolean('descargar') ? 'DESCARGAR' : 'VER', 'Se '.($r->boolean('descargar') ? 'descargó' : 'visualizó').' el documento «'.$d->nombre.'».');
        $b->detalle($r, 'DOCUMENTO', $id);

        return $a->respuesta($d->archivo_path, $d->nombre_archivo, $r->boolean('descargar'), $d->tipo_mime);
    }
}
