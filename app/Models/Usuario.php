<?php

namespace App\Models;

use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /** Constantes de rol (espejo del enum RolUsuario). */
    public const ROLE_CLIENTE = 0;

    public const ROLE_ADMIN_TEC = 1;

    public const ROLE_ADMIN_COM = 2;

    public const ROLE_TECNICO = 3;

    protected $table = 'usuarios';

    protected $fillable = [
        'dependencia_id', 'numero_empleado', 'rol', 'nombre', 'apellidos',
        'email', 'password', 'estado', 'ultimo_acceso', 'remember_token', 'current_device',
    ];

    protected $hidden = ['password', 'remember_token'];

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

    public function isAdminTec(): bool
    {
        return $this->rol === RolUsuario::AdminTec->value;
    }

    public function isAdminCom(): bool
    {
        return $this->rol === RolUsuario::AdminCom->value;
    }

    public function isTecnico(): bool
    {
        return $this->rol === RolUsuario::Tecnico->value;
    }

    public function isCliente(): bool
    {
        return $this->rol === RolUsuario::Cliente->value;
    }

    /** Solo usuarios activos. */
    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }

    /** Filtra por uno o varios roles. */
    public function scopeByRole($query, int ...$roles)
    {
        return $query->whereIn('rol', $roles);
    }

    /** Usuarios de rol administrativo (técnico o comercial). */
    public function scopeAdmins($query)
    {
        return $query->whereIn('rol', [self::ROLE_ADMIN_TEC, self::ROLE_ADMIN_COM]);
    }
}
