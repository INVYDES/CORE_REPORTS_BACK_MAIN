<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    use BelongsToDependencia;

    protected $table = 'movimientos_inventario';

    public $timestamps = false;

    protected $fillable = ['dependencia_id', 'material_id', 'tipo', 'cantidad', 'referencia_tipo', 'referencia_id', 'usuario_id', 'notas', 'created_at'];

    protected $casts = ['cantidad' => 'decimal:2', 'created_at' => 'datetime'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(MaterialCatalogo::class, 'material_id');
    }
}
