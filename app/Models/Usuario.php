<?php

namespace App\Models;

use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property int $dependencia_id
 * @property string|null $numero_empleado
 * @property int $rol
 * @property string $nombre
 * @property string $apellidos
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property bool $estado
 * @property \Illuminate\Support\Carbon|null $ultimo_acceso
 * @property string|null $remember_token
 * @property string|null $current_device
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Dependencia|null $dependencia
 */
class Usuario extends Authenticatable
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /** Constantes de rol (espejo del enum RolUsuario). */
    public const ROLE_CLIENTE = 0;

    public const ROLE_ADMIN_TEC = 1;

    public const ROLE_ADMIN_COM = 2;

    public const ROLE_TECNICO = 3;

    public const ROLE_SUPER_ADMIN = 4;

    protected $table = 'usuarios';

    protected $fillable = [
        'dependencia_id', 'numero_empleado', 'rol', 'nombre', 'apellidos',
        'email', 'email_verified_at', 'password', 'estado', 'ultimo_acceso',
        'remember_token', 'current_device',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'ultimo_acceso' => 'datetime',
        'password' => 'hashed',
        'estado' => 'boolean',
        'rol' => 'integer',
    ];

    public function getIdUsuarioAttribute(): int
    {
        return $this->id;
    }

    public function getIdDependenciaAttribute(): ?int
    {
        return $this->dependencia_id;
    }

    public function getCorreoAttribute(): string
    {
        return $this->email;
    }

    /** @return BelongsTo<Dependencia, $this> */
    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    /** @return HasMany<VerificacionCorreoToken, $this> */
    public function verificacionesCorreo(): HasMany
    {
        return $this->hasMany(VerificacionCorreoToken::class, 'usuario_id');
    }

    public function correoVerificado(): bool
    {
        return $this->email_verified_at !== null;
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

    public function isSuperAdmin(): bool
    {
        return $this->rol === RolUsuario::SuperAdmin->value;
    }

    /**
     * Solo usuarios activos.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    /**
     * Filtra por uno o varios roles.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByRole(Builder $query, int ...$roles): Builder
    {
        return $query->whereIn('rol', $roles);
    }

    /**
     * Usuarios de rol administrativo (técnico o comercial).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereIn('rol', [self::ROLE_ADMIN_TEC, self::ROLE_ADMIN_COM]);
    }
}
