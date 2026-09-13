<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dependencia extends Model
{
    use HasFactory;

    protected $table = 'dependencias';
    protected $primaryKey = 'id_dependencia';

    protected $fillable = [
        'nombre',
        'rfc',
        'tipo_licencia',
        'fecha_expiracion_licencia',
        'correo_contacto',
        'correo_reportes',
        'telefono',
        'datos_facturacion',
        'activo',
        'fecha_registro',
        'nivel_usuarios',
        'limite_usuarios',
        'logo',
    ];

    protected $casts = [
        'activo'                    => 'boolean',
        'limite_usuarios'           => 'integer',
        'fecha_registro'            => 'date',
        'fecha_expiracion_licencia' => 'date',
    ];

    /**
     * Relación: Una empresa tiene muchos empleados/usuarios.
     */
    public function usuarios()
    {
        return $this->hasMany(User::class, 'id_dependencia', 'id_dependencia');
    }
}
