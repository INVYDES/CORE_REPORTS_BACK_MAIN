<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteMaterial extends Model
{
    use BelongsToDependencia;

    protected $table = 'reporte_materiales';

    public $timestamps = false;

    protected $fillable = ['dependencia_id', 'reporte_id', 'material_id', 'cantidad', 'costo_unitario', 'created_at'];

    protected $casts = ['cantidad' => 'decimal:2', 'costo_unitario' => 'decimal:2', 'created_at' => 'datetime'];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MaterialCatalogo::class, 'material_id');
    }
}
