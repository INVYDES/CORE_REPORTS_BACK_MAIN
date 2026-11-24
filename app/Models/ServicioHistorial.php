<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int $servicio_id
 * @property string|null $estatus_anterior
 * @property string|null $estatus_nuevo
 * @property int $cambiado_por
 * @property \Illuminate\Support\Carbon|null $fecha_cambio
 */
class ServicioHistorial extends Model
{
    use BelongsToDependencia;

    protected $table = 'servicio_historial';

    public $timestamps = false;

    protected $fillable = ['dependencia_id', 'servicio_id', 'estatus_anterior', 'estatus_nuevo', 'cambiado_por', 'fecha_cambio'];

    protected $casts = ['fecha_cambio' => 'datetime'];

    /** @return BelongsTo<Servicio, $this> */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }
}
