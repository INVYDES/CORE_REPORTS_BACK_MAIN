<?php

namespace App\Models;

use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = [
        'dependencia_id','numero_empleado','rol','nombre','apellidos',
        'email','password','estado','ultimo_acceso','remember_token'
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'ultimo_acceso' => 'datetime',
        'password' => 'hashed',
        'estado' => 'boolean',
        'rol' => 'integer',
    ];

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    public function getRolEnumAttribute(): RolUsuario
    {
        return RolUsuario::from($this->rol);
    }

    public function hasRol(RolUsuario $rol): bool
    {
        return $this->rol === $rol->value;
    }

    public function isAdminTec(): bool { return $this->rol === RolUsuario::AdminTec->value; }
    public function isAdminCom(): bool { return $this->rol === RolUsuario::AdminCom->value; }
    public function isTecnico(): bool { return $this->rol === RolUsuario::Tecnico->value; }
    public function isCliente(): bool { return $this->rol === RolUsuario::Cliente->value; }
}
