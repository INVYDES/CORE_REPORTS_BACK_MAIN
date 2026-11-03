<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use App\Traits\RegistraBitacora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $unidad_base
 * @property string $costo_unitario
 * @property string $stock_actual
 * @property string $stock_minimo
 * @property bool $activo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MovimientoInventario> $movimientos
 */
class MaterialCatalogo extends Model
{
    use BelongsToDependencia, RegistraBitacora, SoftDeletes;

    protected $table = 'materiales_catalogo';

    protected $fillable = ['dependencia_id', 'nombre', 'descripcion', 'unidad_base', 'costo_unitario', 'stock_actual', 'stock_minimo', 'activo'];

    protected $casts = ['costo_unitario' => 'decimal:2', 'stock_actual' => 'decimal:2', 'stock_minimo' => 'decimal:2', 'activo' => 'boolean'];

    /** @return HasMany<MovimientoInventario, $this> */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'material_id');
    }
}
