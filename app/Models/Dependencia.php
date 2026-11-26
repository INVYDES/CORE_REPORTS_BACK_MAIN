<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $rfc
 * @property string $tipo_licencia
 * @property \Illuminate\Support\Carbon|null $fecha_expiracion
 * @property \Illuminate\Support\Carbon|null $trial_usado_at
 * @property int $limite_usuarios
 * @property int $limite_reportes_mensuales
 * @property string|null $correo_contacto
 * @property string|null $correo_reportes
 * @property string|null $telefono
 * @property string|null $datos_facturacion
 * @property bool $activa
 * @property bool $es_plataforma
 * @property string|null $logo
 * @property string|null $logo_ruta
 * @property string|null $logo_disco
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Dependencia extends Model
{
    use SoftDeletes;

    protected $table = 'dependencias';

    protected $fillable = [
        'nombre', 'rfc', 'tipo_licencia', 'fecha_expiracion',
        'limite_usuarios', 'limite_reportes_mensuales',
        'correo_contacto', 'correo_reportes', 'telefono', 'datos_facturacion', 'activa', 'es_plataforma', 'logo',
        'logo_ruta', 'logo_disco', 'trial_usado_at',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
        'smtp_password', 'smtp_from_address', 'smtp_from_name', 'smtp_activo',
    ];

    protected $hidden = [
        'smtp_password',
    ];

    protected $casts = [
        'fecha_expiracion' => 'date',
        'trial_usado_at' => 'datetime',
        'activa' => 'boolean',
        'es_plataforma' => 'boolean',
        'limite_usuarios' => 'integer',
        'limite_reportes_mensuales' => 'integer',
        'smtp_port' => 'integer',
        'smtp_activo' => 'boolean',
    ];

    public function tieneSmtpConfigurado(): bool
    {
        return (bool) $this->smtp_activo
            && ! empty($this->smtp_host)
            && ! empty($this->smtp_port)
            && ! empty($this->smtp_username)
            && ! empty($this->smtp_password);
    }

    public function smtpPasswordDecrypted(): ?string
    {
        if (empty($this->smtp_password)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $this->smtp_password);
        } catch (\Throwable) {
            return (string) $this->smtp_password;
        }
    }

    public function smtpFromAddress(): string
    {
        $from = trim((string) ($this->smtp_from_address ?: $this->smtp_username ?: config('mail.from.address')));

        return $from !== '' ? $from : 'no-reply@corereports.com';
    }

    public function smtpFromName(): string
    {
        $name = trim((string) ($this->smtp_from_name ?: $this->nombre ?: config('mail.from.name')));

        return $name !== '' ? $name : 'CoreReports';
    }

    public function configurarMailerDinamico(): string
    {
        $mailerName = 'empresa_smtp_' . ($this->id ?: 'temp');
        $enc = strtolower(trim((string) ($this->smtp_encryption ?? 'tls')));
        $scheme = $enc === 'ssl' ? 'smtps' : 'smtp';

        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'scheme' => $scheme,
                'host' => trim((string) $this->smtp_host),
                'port' => (int) ($this->smtp_port ?: 587),
                'encryption' => in_array($enc, ['tls', 'ssl'], true) ? $enc : null,
                'username' => trim((string) $this->smtp_username),
                'password' => $this->smtpPasswordDecrypted(),
                'timeout' => 15,
            ],
        ]);

        try {
            Mail::purge($mailerName);
        } catch (\Throwable) {
            // Ignorar si la versión de MailManager no requiere purge
        }

        return $mailerName;
    }

    public function getIdDependenciaAttribute(): int
    {
        return $this->id;
    }

    public function getActivoAttribute(): bool
    {
        return (bool) $this->activa;
    }

    public function getFechaExpiracionLicenciaAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->fecha_expiracion;
    }

    /** @return HasMany<Usuario, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'dependencia_id');
    }

    /** @return HasOne<Suscripcion, $this> */
    public function suscripcion(): HasOne
    {
        return $this->hasOne(Suscripcion::class, 'dependencia_id');
    }

    /** @return HasMany<Area, $this> */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, 'dependencia_id');
    }

    /** @return HasMany<Equipo, $this> */
    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'dependencia_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'dependencia_id');
    }

    /** @return HasMany<Servicio, $this> */
    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'dependencia_id');
    }

    /** @return HasMany<Reporte, $this> */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'dependencia_id');
    }
}
