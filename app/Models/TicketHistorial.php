<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int $ticket_id
 * @property string|null $estatus_anterior
 * @property string|null $estatus_nuevo
 * @property int $cambiado_por
 * @property \Illuminate\Support\Carbon|null $fecha_cambio
 */
class TicketHistorial extends Model
{
    use BelongsToDependencia;

    protected $table = 'ticket_historial';

    public $timestamps = false;

    protected $fillable = ['dependencia_id', 'ticket_id', 'estatus_anterior', 'estatus_nuevo', 'cambiado_por', 'fecha_cambio'];

    protected $casts = ['fecha_cambio' => 'datetime'];

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cambiado_por');
    }
}
