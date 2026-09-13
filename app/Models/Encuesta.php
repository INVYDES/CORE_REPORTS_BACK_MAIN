<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Encuesta extends Model
{
    use HasFactory;

    protected $table = 'encuestas';
    protected $primaryKey = 'id_encuesta';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_reporte',
        'calificacion',
        'comentarios',
        'nombre_firma',
        'fecha',
    ];

    public function reporte()
    {
        return $this->belongsTo(Reporte::class, 'id_reporte', 'id_reporte');
    }
}
