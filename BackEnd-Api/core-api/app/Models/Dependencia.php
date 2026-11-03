<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $rfc
 * @property string $tipo_licencia
 * @property \Illuminate\Support\Carbon|null $fecha_expiracion
 * @property \Illuminate\Support\Carbon|null $trial_usado_at
 * @property int $limite_usuarios
 * @property int $limite_reportes_mensuales
 * @property string|null $correo_contacto
 * @property string|null $correo_reportes
 * @property string|null $telefono
 * @property string|null $datos_facturacion
 * @property bool $activa
 * @property string|null $logo
 * @property string|null $logo_ruta
 * @property string|null $logo_disco
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Dependencia extends Model
{
    use SoftDeletes;

    protected $table = 'dependencias';

    protected $fillable = [
        'nombre', 'rfc', 'tipo_licencia', 'fecha_expiracion',
        'limite_usuarios', 'limite_reportes_mensuales',
        'correo_contacto', 'correo_reportes', 'telefono', 'datos_facturacion', 'activa', 'logo',
        'logo_ruta', 'logo_disco', 'trial_usado_at',
    ];

    protected $casts = [
        'fecha_expiracion' => 'date',
        'trial_usado_at' => 'datetime',
        'activa' => 'boolean',
        'limite_usuarios' => 'integer',
        'limite_reportes_mensuales' => 'integer',
    ];

    public function getIdDependenciaAttribute(): int
    {
        return $this->id;
    }

    public function getActivoAttribute(): bool
    {
        return (bool) $this->activa;
    }

    public function getFechaExpiracionLicenciaAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->fecha_expiracion;
    }

    /** @return HasMany<Usuario, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'dependencia_id');
    }

    /** @return HasMany<Area, $this> */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, 'dependencia_id');
    }

    /** @return HasMany<Equipo, $this> */
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'dependencia_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'dependencia_id');
    }

    /** @return HasMany<Servicio, $this> */
    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'dependencia_id');
    }

    /** @return HasMany<Reporte, $this> */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'dependencia_id');
    }
}
