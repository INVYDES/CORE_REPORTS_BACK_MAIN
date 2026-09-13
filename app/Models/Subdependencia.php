<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subdependencia extends Model
{
    use HasFactory;

    protected $table = 'subdependencias';
    protected $primaryKey = 'id_subdependencia';

    protected $fillable = [
        'id_dependencia',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class, 'id_dependencia', 'id_dependencia');
    }
}
