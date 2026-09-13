<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    use HasFactory;

    protected $table = 'reportes';
    protected $primaryKey = 'id_reporte';

    protected $fillable = [
        'folio',
        'id_dependencia',
        'id_subdependencia',
        'creado_por',
        'tipo_reporte',
        'origen_datos_id',
        'categoria',
        'fecha_elaboracion',
        'responsable_id',
        'responsable_texto',
        'cargo_texto',
        'fecha_hora_inicio',
        'fecha_hora_fin',
        'hora_salida_base',
        'hora_llegada_sitio',
        'hora_regreso_a_base',
        'falla_reportada',
        'desarrollo_actividades',
        'es_retrabajo',
        'estatus_reporte',
    ];

    protected $casts = [
        'es_retrabajo' => 'boolean',
    ];

    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class, 'id_dependencia', 'id_dependencia');
    }

    public function subdependencia()
    {
        return $this->belongsTo(Subdependencia::class, 'id_subdependencia', 'id_subdependencia');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por', 'id_usuario');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id', 'id_usuario');
    }

    public function materiales()
    {
        return $this->hasMany(ReporteMaterial::class, 'id_reporte', 'id_reporte');
    }

    public function evidencias()
    {
        return $this->hasMany(ReporteEvidencia::class, 'id_reporte', 'id_reporte');
    }

    public function ejecutores()
    {
        return $this->hasMany(ReporteEjecutor::class, 'id_reporte', 'id_reporte');
    }

    public function encuesta()
    {
        return $this->hasOne(Encuesta::class, 'id_reporte', 'id_reporte');
    }
}
