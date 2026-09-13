<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteMaterial extends Model
{
    use HasFactory;

    protected $table = 'reporte_materiales';
    protected $primaryKey = 'id_item';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_reporte',
        'material',
        'cantidad',
        'unidad',
    ];

    public function reporte()
    {
        return $this->belongsTo(Reporte::class, 'id_reporte', 'id_reporte');
    }
}
