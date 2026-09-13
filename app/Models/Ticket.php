<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    // 1. Nombre exacto de la tabla en tu archivo database_schema.sql
    protected $table = 'tickets';

    // 2. Lista blanca de columnas editables desde formularios
    protected $fillable = [
        'folio',
        'id_dependencia',
        'id_subdependencia',
        'asunto',
        'descripcion',
        'solicitante',
        'cargo',
        'prioridad',
        'estatus',
        'fecha_solicitud',
        'fecha_atencion',
        'atendio',
        'id_tecnico_atendio',
    ];

    // 3. Relación: El ticket pertenece a una empresa (dependencia)
    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class, 'id_dependencia', 'id_dependencia');
    }

    // 4. Relación: El ticket puede tener un técnico asignado
    public function tecnico()
    {
        return $this->belongsTo(User::class, 'id_tecnico_atendio', 'id_usuario');
    }
}