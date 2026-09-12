<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteEjecutor extends Model
{
    use BelongsToDependencia;

    protected $table = 'reporte_ejecutores';

    public $timestamps = false;

    protected $fillable = ['dependencia_id', 'reporte_id', 'usuario_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
