<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialModificacion extends Model
{
    use HasFactory;

    protected $table = 'historial_modificaciones';
    protected $primaryKey = 'id_modificacion';
    public $timestamps = false; // Usa columna personalizada fecha_modificacion

    protected $fillable = [
        'entidad_tipo',
        'entidad_id',
        'id_usuario',
        'descripcion',
        'fecha_modificacion',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
