<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReporteEvidencia;
use App\Services\EvidenceUrlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class EvidenciaController extends Controller
{
    public function __construct(private readonly EvidenceUrlService $urls) {}

    /**
     * GET /api/v1/evidencias/{evidencia}/documento
     *
     * Descarga/visualiza un documento mediante URL firmada. La ruta se
     * registra fuera del middleware de sesion para que funcione desde enlaces
     * directos (correo, PDF, etc.).
     */
    public function documento(Request $request, int $evidencia): Response
    {
        $registro = ReporteEvidencia::withoutGlobalScopes()->findOrFail($evidencia);

        if ($registro->isImageEvidence()) {
            abort(404);
        }

        $resolved = $this->urls->resolve($registro);

        if ($resolved === null) {
            $raw = $registro->getRawOriginal('url');

            if (is_string($raw) && $raw !== '') {
                return redirect()->away($raw);
            }

            abort(404);
        }

        $disk = Storage::disk($resolved['disk']);

        if (! $disk->exists($resolved['path'])) {
            $raw = $registro->getRawOriginal('url');

            if (is_string($raw) && $raw !== '') {
                return redirect()->away($raw);
            }

            abort(404);
        }

        $extension = pathinfo($resolved['path'], PATHINFO_EXTENSION);
        $name = 'evidencia-'.$registro->id.($extension !== '' ? '.'.$extension : '');
        $mime = $registro->tipo_archivo ?: $disk->mimeType($resolved['path']) ?: 'application/octet-stream';

        return $disk->response($resolved['path'], $name, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
