<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Encuesta extends Model
{
    use BelongsToDependencia;

    protected $table = 'encuestas';
    public $timestamps = false;
    protected $fillable = ['dependencia_id','reporte_id','calificacion','comentario','respondido_por','created_at'];
    protected $casts = ['calificacion'=>'integer','created_at'=>'datetime'];

    public function reporte(): BelongsTo { return $this->belongsTo(Reporte::class, 'reporte_id'); }
}
