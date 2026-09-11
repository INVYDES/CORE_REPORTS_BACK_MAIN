<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCatalogo extends Model
{
    use SoftDeletes, BelongsToDependencia;
    protected $table = 'materiales_catalogo';
    protected $fillable = ['dependencia_id','nombre','descripcion','unidad_base','costo_unitario','stock_actual','activo'];
    protected $casts = ['costo_unitario'=>'decimal:2','stock_actual'=>'decimal:2','activo'=>'boolean'];
    public function movimientos(): HasMany { return $this->hasMany(MovimientoInventario::class,'material_id'); }
}
