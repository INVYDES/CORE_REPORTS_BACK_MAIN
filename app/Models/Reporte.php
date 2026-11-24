<?php

namespace App\Models;

use App\Traits\BelongsToDependencia;
use App\Traits\GeneraFolio;
use App\Traits\RegistraBitacora;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property int|null $area_id
 * @property int|null $equipo_id
 * @property string $folio
 * @property int $creado_por
 * @property int|null $responsable_id
 * @property int|null $ticket_id
 * @property int|null $servicio_id
 * @property string $tipo
 * @property string|null $categoria
 * @property \Illuminate\Support\Carbon $fecha_inicio
 * @property \Illuminate\Support\Carbon $fecha_fin
 * @property int|null $minutos_trabajados
 * @property string|null $desarrollo
 * @property string $estatus
 * @property string $costo_mano_obra
 * @property string $costo_materiales
 * @property \Illuminate\Support\Carbon|null $hora_salida
 * @property \Illuminate\Support\Carbon|null $hora_llegada
 * @property \Illuminate\Support\Carbon|null $hora_inicio_diagnostico
 * @property \Illuminate\Support\Carbon|null $hora_inicio_trabajo
 * @property \Illuminate\Support\Carbon|null $hora_fin_trabajo
 * @property \Illuminate\Support\Carbon|null $hora_regreso
 * @property bool $es_retrabajo
 * @property int|null $reporte_origen_id
 * @property string $conformidad_estatus
 * @property string|null $conformidad_firmado_por
 * @property \Illuminate\Support\Carbon|null $conformidad_fecha
 * @property bool|null $ftfr
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Usuario|null $creador
 * @property-read Usuario|null $responsable
 * @property-read Ticket|null $ticket
 * @property-read Servicio|null $servicio
 * @property-read Area|null $area
 * @property-read Equipo|null $equipo
 * @property-read Reporte|null $origen
 * @property-read Dependencia|null $dependencia
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ReporteEjecutor> $ejecutores
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ReporteMaterial> $materiales
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ReporteEvidencia> $evidencias
 */
class Reporte extends Model
{
    use BelongsToDependencia, GeneraFolio, RegistraBitacora, SoftDeletes;

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

    /** @return BelongsTo<Usuario, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /** @return BelongsTo<Servicio, $this> */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    /** @return BelongsTo<self, $this> */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporte_origen_id');
    }

    /** @return HasMany<ReporteEjecutor, $this> */
    public function ejecutores(): HasMany
    {
        return $this->hasMany(ReporteEjecutor::class, 'reporte_id');
    }

    /** @return HasMany<ReporteMaterial, $this> */
    public function materiales(): HasMany
    {
        return $this->hasMany(ReporteMaterial::class, 'reporte_id');
    }

    /** @return HasMany<ReporteEvidencia, $this> */
    public function evidencias(): HasMany
    {
        return $this->hasMany(ReporteEvidencia::class, 'reporte_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePorTipo(Builder $query, ?string $tipo): Builder
    {
        return $tipo ? $query->where('tipo', $tipo) : $query;
    }

    /** Folio único por dependencia: REP-YYYYMM-XXXXX (secuencial mensual). */
    public static function generarFolio(int $dependenciaId): string
    {
        $prefijo = 'REP-'.now()->format('Ym');

        // Secuencia serializada por dependencia; ver Ticket::generarFolio. El
        // alta de reportes ya genera el folio dentro de su transacción.
        DB::table('dependencias')->where('id', $dependenciaId)->lockForUpdate()->first();

        $ultimo = static::withoutGlobalScope(\App\Scopes\DependenciaScope::class)
            ->where('dependencia_id', $dependenciaId)
            ->where('folio', 'like', $prefijo.'-%')
            ->orderByDesc('folio')
            ->lockForUpdate()
            ->value('folio');

        $secuencia = $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1;

        return sprintf('%s-%05d', $prefijo, $secuencia);
    }
}
