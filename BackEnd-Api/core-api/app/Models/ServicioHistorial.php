<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioHistorial extends Model
{
    use BelongsToDependencia;
    protected $table = 'servicio_historial';
    public $timestamps = false;
    protected $fillable = ['dependencia_id','servicio_id','estatus_anterior','estatus_nuevo','cambiado_por','fecha_cambio'];
    protected $casts = ['fecha_cambio' => 'datetime'];
    public function servicio(): BelongsTo { return $this->belongsTo(Servicio::class, 'servicio_id'); }
}
