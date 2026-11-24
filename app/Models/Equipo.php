<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int|null $area_id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $marca
 * @property string|null $modelo
 * @property string|null $numero_serie
 * @property \Illuminate\Support\Carbon|null $fecha_instalacion
 * @property bool $activo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Area|null $area
 */
class Equipo extends Model
{
    use BelongsToDependencia, SoftDeletes;

    protected $table = 'equipos';

    protected $fillable = ['dependencia_id', 'area_id', 'codigo', 'nombre', 'marca', 'modelo', 'numero_serie', 'fecha_instalacion', 'activo'];

    protected $casts = ['activo' => 'boolean', 'fecha_instalacion' => 'date'];

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
}
