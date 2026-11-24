<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $activa
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read int|null $tickets_count
 * @property-read int|null $servicios_count
 */
class Area extends Model
{
    use BelongsToDependencia, SoftDeletes;

    protected $table = 'areas';

    protected $fillable = ['dependencia_id', 'codigo', 'nombre', 'descripcion', 'activa'];

    protected $casts = ['activa' => 'boolean'];

    /** @return HasMany<Equipo, $this> */
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'area_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'area_id');
    }

    /** @return HasMany<Servicio, $this> */
    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'area_id');
    }

    /** @return HasMany<Reporte, $this> */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'area_id');
    }
}
