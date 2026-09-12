<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use App\Traits\RegistraBitacora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reporte extends Model
{
    use BelongsToDependencia, RegistraBitacora, SoftDeletes;

    protected $table = 'reportes';

    protected $fillable = [
        'dependencia_id', 'area_id', 'equipo_id', 'folio', 'creado_por', 'responsable_id',
        'ticket_id', 'servicio_id', 'tipo', 'categoria', 'fecha_inicio', 'fecha_fin',
        'desarrollo', 'estatus', 'costo_mano_obra', 'costo_materiales',
        'hora_salida', 'hora_llegada', 'hora_inicio_diagnostico', 'hora_inicio_trabajo', 'hora_fin_trabajo', 'hora_regreso',
        'es_retrabajo', 'reporte_origen_id', 'conformidad_estatus', 'conformidad_firmado_por', 'conformidad_fecha', 'ftfr',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'hora_salida' => 'datetime',
        'hora_llegada' => 'datetime',
        'hora_inicio_diagnostico' => 'datetime',
        'hora_inicio_trabajo' => 'datetime',
        'hora_fin_trabajo' => 'datetime',
        'hora_regreso' => 'datetime',
        'conformidad_fecha' => 'datetime',
        'es_retrabajo' => 'boolean',
        'ftfr' => 'boolean',
        'costo_mano_obra' => 'decimal:2',
        'costo_materiales' => 'decimal:2',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporte_origen_id');
    }

    public function ejecutores(): HasMany
    {
        return $this->hasMany(ReporteEjecutor::class, 'reporte_id');
    }

    public function materiales(): HasMany
    {
        return $this->hasMany(ReporteMaterial::class, 'reporte_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(ReporteEvidencia::class, 'reporte_id');
    }

    public function scopePorTipo($q, $tipo)
    {
        return $tipo ? $q->where('tipo', $tipo) : $q;
    }

    /** Folio único por dependencia: REP-YYYYMM-XXXXX (secuencial mensual). */
    public static function generarFolio(int $dependenciaId): string
    {
        $prefijo = 'REP-'.now()->format('Ym');

        $ultimo = static::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->where('dependencia_id', $dependenciaId)
            ->where('folio', 'like', $prefijo.'-%')
            ->orderByDesc('folio')
            ->value('folio');

        $secuencia = $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1;

        return sprintf('%s-%05d', $prefijo, $secuencia);
    }
}
