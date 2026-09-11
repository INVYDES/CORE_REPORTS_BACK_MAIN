<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketHistorial extends Model
{
    use BelongsToDependencia;

    protected $table = 'ticket_historial';
    public $timestamps = false;
    protected $fillable = ['dependencia_id','ticket_id','estatus_anterior','estatus_nuevo','cambiado_por','fecha_cambio'];
    protected $casts = ['fecha_cambio' => 'datetime'];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class, 'ticket_id'); }
    public function usuario(): BelongsTo { return $this->belongsTo(Usuario::class, 'cambiado_por'); }
}
