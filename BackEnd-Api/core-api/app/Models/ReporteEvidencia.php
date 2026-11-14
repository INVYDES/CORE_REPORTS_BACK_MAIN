<?php

namespace App\Models;

use App\Services\EvidenceUrlService;
use App\Services\UploadStorageService;
use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int $reporte_id
 * @property string $url
 * @property string|null $ruta
 * @property string|null $disco
 * @property string|null $tipo_archivo
 * @property int|null $peso_kb
 * @property int|null $subido_por
 * @property \Illuminate\Support\Carbon|null $fecha_subida
 * @property-read Reporte|null $reporte
 */
class ReporteEvidencia extends Model
{
    use BelongsToDependencia;

    protected $table = 'reporte_evidencias';

    public $timestamps = false;

    protected $fillable = [
        'dependencia_id', 'reporte_id', 'url', 'ruta', 'disco', 'tipo_archivo',
        'peso_kb', 'subido_por', 'fecha_subida',
    ];

    protected $casts = ['fecha_subida' => 'datetime'];

    /**
     * La URL expuesta al frontend depende del tipo de archivo: imagenes
     * publicas y documentos con URL firmada temporal.
     */
    public function getUrlAttribute(mixed $value): ?string
    {
        try {
            if (class_exists(\App\Services\EvidenceUrlService::class)) {
                return app(\App\Services\EvidenceUrlService::class)->forEvidence($this);
            }
        } catch (\Throwable) {
            // fallback defensivo si el servicio no resuelve
        }

        $raw = $this->getRawOriginal('url');
        if ($raw) {
            return (string) $raw;
        }

        if ($this->ruta) {
            return '/storage/' . ltrim($this->ruta, '/');
        }

        return $value ? (string) $value : null;
    }

    public function isImageEvidence(): bool
    {
        try {
            if (class_exists(\App\Services\UploadStorageService::class)) {
                return app(\App\Services\UploadStorageService::class)->isImageType($this->tipo_archivo, $this->ruta);
            }
        } catch (\Throwable) {
            // fallback
        }

        if ($this->tipo_archivo && str_starts_with($this->tipo_archivo, 'image/')) {
            return true;
        }

        $ext = strtolower(pathinfo((string) ($this->ruta ?: $this->getRawOriginal('url')), PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /**
     * Imagen incrustable en el PDF (data-URI) leida del disco propio.
     *
     * Devuelve null para referencias remotas o archivos que no son imagenes
     * raster, de modo que la generacion del PDF nunca haga peticiones HTTP.
     */
    public function pdfImage(): ?string
    {
        $uploads = app(UploadStorageService::class);
        $disk = $this->disco ?: null;

        $ruta = $this->ruta;
        if (! $ruta && method_exists($uploads, 'pathFromUrl')) {
            $ruta = $uploads->pathFromUrl((string) $this->getRawOriginal('url'), $disk);
        }

        if ($ruta && method_exists($uploads, 'inlineImage')) {
            try {
                return $uploads->inlineImage($ruta, $disk);
            } catch (\Throwable) {
                // continuar al fallback directo
            }
        }

        if (! $ruta) {
            return null;
        }

        try {
            $cleanPath = ltrim(trim($ruta), '/');
            if (\Illuminate\Support\Str::startsWith($cleanPath, 'storage/')) {
                $cleanPath = substr($cleanPath, strlen('storage/'));
            }
            if (\Illuminate\Support\Str::startsWith($cleanPath, 'data:image')) {
                return $cleanPath;
            }
            $diskName = $disk ?: (method_exists($uploads, 'diskName') ? $uploads->diskName() : 'public');
            if (\Illuminate\Support\Facades\Storage::disk($diskName)->exists($cleanPath)) {
                $bytes = \Illuminate\Support\Facades\Storage::disk($diskName)->get($cleanPath);
                if (is_string($bytes) && $bytes !== '') {
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg';
                    return 'data:'.$mime.';base64,'.base64_encode($bytes);
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /** @return BelongsTo<Reporte, $this> */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }
}
