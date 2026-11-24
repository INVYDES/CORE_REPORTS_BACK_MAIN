<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int $material_id
 * @property string $tipo
 * @property string $cantidad
 * @property string $referencia_tipo
 * @property int|null $referencia_id
 * @property int|null $usuario_id
 * @property string|null $notas
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read MaterialCatalogo|null $material
 */
class MovimientoInventario extends Model
{
    use BelongsToDependencia;

    protected $table = 'movimientos_inventario';

    public $timestamps = false;

    protected $fillable = ['dependencia_id', 'material_id', 'tipo', 'cantidad', 'referencia_tipo', 'referencia_id', 'usuario_id', 'notas', 'created_at'];

    protected $casts = ['cantidad' => 'decimal:2', 'created_at' => 'datetime'];

    /** @return BelongsTo<MaterialCatalogo, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(MaterialCatalogo::class, 'material_id');
    }
}
