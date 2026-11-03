<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int $reporte_id
 * @property int $material_id
 * @property string $cantidad
 * @property string $costo_unitario
 * @property string|null $subtotal
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read MaterialCatalogo|null $material
 * @property-read Reporte|null $reporte
 */
class ReporteMaterial extends Model
{
    use BelongsToDependencia;

    protected $table = 'reporte_materiales';

    public $timestamps = false;

    protected $fillable = ['dependencia_id', 'reporte_id', 'material_id', 'cantidad', 'costo_unitario', 'created_at'];

    protected $casts = ['cantidad' => 'decimal:2', 'costo_unitario' => 'decimal:2', 'created_at' => 'datetime'];

    /** @return BelongsTo<Reporte, $this> */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }

    /** @return BelongsTo<MaterialCatalogo, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(MaterialCatalogo::class, 'material_id');
    }
}
