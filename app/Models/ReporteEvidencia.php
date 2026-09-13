<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteEvidencia extends Model
{
    use HasFactory;

    protected $table = 'reporte_evidencias';
    protected $primaryKey = 'id';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_reporte',
        'url',
        'comentario',
    ];

    public function reporte()
    {
        return $this->belongsTo(Reporte::class, 'id_reporte', 'id_reporte');
    }
}
