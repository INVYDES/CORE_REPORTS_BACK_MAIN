<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteEvidencia extends Model
{
    use BelongsToDependencia;
    protected $table = 'reporte_evidencias';
    public $timestamps = false;
    protected $fillable = ['dependencia_id','reporte_id','url','tipo_archivo','peso_kb','subido_por','fecha_subida'];
    protected $casts = ['fecha_subida'=>'datetime'];
    public function reporte(): BelongsTo { return $this->belongsTo(Reporte::class, 'reporte_id'); }
}
