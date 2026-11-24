<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int $reporte_id
 * @property int $usuario_id
 * @property int|null $minutos_invertidos
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read Usuario|null $usuario
 * @property-read Reporte|null $reporte
 */
class ReporteEjecutor extends Model
{
    use BelongsToDependencia;

    protected $table = 'reporte_ejecutores';

    public $timestamps = false;

    protected $fillable = ['dependencia_id', 'reporte_id', 'usuario_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    /** @return BelongsTo<Reporte, $this> */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
