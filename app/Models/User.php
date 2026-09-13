<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';

    /**
     * Atributos asignables masivamente.
     */
    protected $fillable = [
        'id_dependencia',
        'numero_empleado',
        'rol',
        'nombre',
        'apellidos',
        'correo',
        'password',
        'estado',
        'ultimo_acceso',
    ];

    /**
     * Atributos ocultos en serialización JSON (seguridad).
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts de tipos.
     */
    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'estado'        => 'integer',
            'rol'           => 'integer',
            'ultimo_acceso' => 'datetime',
        ];
    }

    /**
     * Relación: El usuario pertenece a una Dependencia (Empresa).
     */
    public function dependencia()
    {
        return $this->belongsTo(Dependencia::class, 'id_dependencia', 'id_dependencia');
    }
}
