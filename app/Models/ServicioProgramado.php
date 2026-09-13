<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicioProgramado extends Model
{
    use HasFactory;

    protected $table = 'servicios_programados';
    protected $primaryKey = 'id';

    protected $fillable = [
        'folio',
        'id_dependencia',
        'id_subdependencia',
        'asunto',
        'descripcion',
        'solicitante',
        'cargo',
        'id_tecnico_asignado',
        'asignado_a',
        'fecha_asignacion',
        'fecha_vencimiento',
        'estatus',
        'prioridad',
    ];

    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class, 'id_dependencia', 'id_dependencia');
    }

    public function subdependencia()
    {
        return $this->belongsTo(Subdependencia::class, 'id_subdependencia', 'id_subdependencia');
    }

    public function tecnico()
    {
        return $this->belongsTo(User::class, 'id_tecnico_asignado', 'id_usuario');
    }
}
