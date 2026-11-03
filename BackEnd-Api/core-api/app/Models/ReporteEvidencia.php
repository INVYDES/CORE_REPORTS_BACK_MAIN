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
        return app(EvidenceUrlService::class)->forEvidence($this);
    }

    public function isImageEvidence(): bool
    {
        return app(UploadStorageService::class)->isImageType($this->tipo_archivo, $this->ruta);
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

        $ruta = $this->ruta ?: $uploads->pathFromUrl((string) $this->getRawOriginal('url'), $disk);

        return $ruta ? $uploads->inlineImage($ruta, $disk) : null;
    }

    /** @return BelongsTo<Reporte, $this> */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }
}
