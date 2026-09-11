<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dependencia extends Model
{
    use SoftDeletes;

    protected $table = 'dependencias';

    protected $fillable = [
        'nombre','rfc','tipo_licencia','fecha_expiracion',
        'limite_usuarios','limite_reportes_mensuales',
        'correo_contacto','correo_reportes','telefono','datos_facturacion','activa'
    ];

    protected $casts = [
        'fecha_expiracion' => 'date',
        'activa' => 'boolean',
        'limite_usuarios' => 'integer',
        'limite_reportes_mensuales' => 'integer',
    ];

    public function usuarios(): HasMany { return $this->hasMany(Usuario::class, 'dependencia_id'); }
    public function areas(): HasMany { return $this->hasMany(Area::class, 'dependencia_id'); }
    public function equipos(): HasMany { return $this->hasMany(Equipo::class, 'dependencia_id'); }
    public function tickets(): HasMany { return $this->hasMany(Ticket::class, 'dependencia_id'); }
    public function servicios(): HasMany { return $this->hasMany(Servicio::class, 'dependencia_id'); }
    public function reportes(): HasMany { return $this->hasMany(Reporte::class, 'dependencia_id'); }
}
