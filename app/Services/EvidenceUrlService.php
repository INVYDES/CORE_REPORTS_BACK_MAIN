<?php

namespace App\Services;

use App\Models\ReporteEvidencia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Resuelve las URLs de las evidencias de un reporte.
 *
 * - Las imagenes se sirven con su URL publica para poder mostrarse inline.
 * - Los documentos se sirven mediante una ruta firmada de corta duracion, de
 *   modo que el bucket puede permanecer privado.
 */
class EvidenceUrlService
{
    public function __construct(private readonly UploadStorageService $uploads) {}

    public function forEvidence(ReporteEvidencia $evidencia): ?string
    {
        $raw = $evidencia->getRawOriginal('url');

        if ($evidencia->isImageEvidence()) {
            return $evidencia->ruta ? $this->uploads->url($evidencia->ruta) : $raw;
        }

        if (! $evidencia->exists) {
            return $raw;
        }

        if ($evidencia->ruta || $this->uploads->pathFromUrl($raw)) {
            return URL::temporarySignedRoute(
                'evidencias.documento',
                now()->addMinutes(max(1, (int) config('filesystems.signed_url_ttl', 60))),
                ['evidencia' => $evidencia->getKey()]
            );
        }

        return $raw;
    }

    /**
     * @return array{disk: string, path: string}|null
     */
    public function resolve(ReporteEvidencia $evidencia): ?array
    {
        $disk = $evidencia->disco ?: $this->uploads->diskName();
        $path = $evidencia->ruta ?: $this->uploads->pathFromUrl($evidencia->getRawOriginal('url'), $disk);

        if ($path === null || $path === '') {
            return null;
        }

        return ['disk' => $disk, 'path' => $path];
    }

    public function exists(ReporteEvidencia $evidencia): bool
    {
        $resolved = $this->resolve($evidencia);

        return $resolved !== null
            && Storage::disk($resolved['disk'])->exists($resolved['path']);
    }
}
