<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteEjecutor extends Model
{
    use HasFactory;

    protected $table = 'reporte_ejecutores';
    protected $primaryKey = 'id';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_reporte',
        'id_usuario',
    ];

    public function reporte()
    {
        return $this->belongsTo(Reporte::class, 'id_reporte', 'id_reporte');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
