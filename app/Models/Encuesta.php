<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int $reporte_id
 * @property int $calificacion
 * @property string|null $comentario
 * @property string|null $respondido_por
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read Reporte|null $reporte
 */
class Encuesta extends Model
{
    use BelongsToDependencia;

    protected $table = 'encuestas';

    public $timestamps = false;

    protected $fillable = ['dependencia_id', 'reporte_id', 'calificacion', 'comentario', 'respondido_por', 'created_at'];

    protected $casts = ['calificacion' => 'integer', 'created_at' => 'datetime'];

    /** @return BelongsTo<Reporte, $this> */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }
}
