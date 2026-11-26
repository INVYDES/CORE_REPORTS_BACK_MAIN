<?php

namespace App\Services;

use App\Mail\BienvenidaMail;
use App\Mail\LicenciaPorVencerMail;
use App\Mail\LicenciaVencidaMail;
use App\Mail\NotificacionMail;
use App\Mail\PagoConfirmadoMail;
use App\Mail\RecuperarPasswordMail;
use App\Mail\VerificacionCuentaMail;
use App\Models\CorreoLog;
use App\Models\Dependencia;
use App\Models\Licencia;
use App\Models\Notificacion;
use App\Models\Usuario;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CorreoAutomatizadoService
{
    /**
     * Indica si una categoria de correo esta habilitada para envio.
     */
    public function habilitado(string $tipo): bool
    {
        return in_array($this->categoria($tipo), (array) config('correo.tipos_habilitados', []), true);
    }

    private function categoria(string $tipo): string
    {
        return match (true) {
            $tipo === 'pago_confirmado' => 'pago',
            str_starts_with($tipo, 'licencia') => 'licencia',
            default => $tipo,
        };
    }

    public function enviarVerificacion(Usuario $usuario, string $codigo, string $enlace, CarbonInterface $expira): ?CorreoLog
    {
        return $this->enviar(
            'verificacion',
            $usuario->email,
            'Verifica tu correo en '.config('app.name'),
            new VerificacionCuentaMail($usuario, $codigo, $enlace, $expira),
            $usuario,
            $usuario->dependencia_id,
            null,
            ['expira' => $expira->toIso8601String()],
        );
    }

    public function enviarBienvenida(Usuario $usuario, ?Licencia $licencia = null): ?CorreoLog
    {
        return $this->enviar(
            'bienvenida',
            $usuario->email,
            'Bienvenido a '.config('app.name'),
            new BienvenidaMail($usuario, $licencia),
            $usuario,
            $usuario->dependencia_id,
            'bienvenida:'.$usuario->id,
            ['licencia' => $licencia?->codigo],
        );
    }

    public function enviarRecuperacion(Usuario $usuario, string $enlace, int $expiraMinutos): ?CorreoLog
    {
        return $this->enviar(
            'recuperacion',
            $usuario->email,
            'Restablece tu contraseña de '.config('app.name'),
            new RecuperarPasswordMail($usuario, $enlace, $expiraMinutos),
            $usuario,
            $usuario->dependencia_id,
            null,
            ['expira_minutos' => $expiraMinutos],
        );
    }

    public function enviarPagoConfirmado(
        Dependencia $dependencia,
        Licencia $licencia,
        float $monto,
        ?string $referencia = null,
    ): ?CorreoLog {
        $destino = $this->destinatarioDependencia($dependencia);

        if ($destino === null) {
            return null;
        }

        [$email, $usuario] = $destino;

        return $this->enviar(
            'pago_confirmado',
            $email,
            'Pago confirmado: '.$licencia->nombre,
            new PagoConfirmadoMail($usuario ?? new Usuario(['nombre' => $dependencia->nombre, 'email' => $email]), $dependencia, $licencia, $monto, $referencia),
            $usuario,
            $dependencia->id,
            $referencia ? 'pago:'.$referencia : null,
            ['licencia_id' => $licencia->id, 'monto' => $monto],
        );
    }

    public function enviarLicenciaPorVencer(Dependencia $dependencia, int $dias): ?CorreoLog
    {
        $destino = $this->destinatarioDependencia($dependencia);

        if ($destino === null || $dependencia->fecha_expiracion === null) {
            return null;
        }

        [$email, $usuario] = $destino;
        $fecha = $dependencia->fecha_expiracion->format('d/m/Y');

        return $this->enviar(
            'licencia_por_vencer',
            $email,
            'Tu licencia vence en '.$dias.' '.($dias === 1 ? 'día' : 'días'),
            new LicenciaPorVencerMail($dependencia, $dias, $fecha),
            $usuario,
            $dependencia->id,
            'licencia-por-vencer:'.$dependencia->id.':'.$dependencia->fecha_expiracion->toDateString().':'.$dias,
            ['dias' => $dias, 'fecha' => $fecha],
        );
    }

    public function enviarLicenciaVencida(Dependencia $dependencia): ?CorreoLog
    {
        $destino = $this->destinatarioDependencia($dependencia);

        if ($destino === null) {
            return null;
        }

        [$email, $usuario] = $destino;
        $fecha = $dependencia->fecha_expiracion?->format('d/m/Y') ?? 'la fecha programada';

        return $this->enviar(
            'licencia_vencida',
            $email,
            'Tu licencia de '.config('app.name').' venció',
            new LicenciaVencidaMail($dependencia, $fecha),
            $usuario,
            $dependencia->id,
            'licencia-vencida:'.$dependencia->id.':'.($dependencia->fecha_expiracion?->toDateString() ?? 'sin-fecha'),
            ['fecha' => $fecha],
        );
    }

    public function enviarNotificacion(Notificacion $notificacion, bool $forzar = false): ?CorreoLog
    {
        if (! $notificacion->destinatario) {
            return null;
        }

        $usuario = $notificacion->usuario_id
            ? Usuario::find($notificacion->usuario_id)
            : null;

        return $this->enviar(
            'notificacion',
            (string) $notificacion->destinatario,
            (string) ($notificacion->asunto ?: 'Notificación '.config('app.name')),
            new NotificacionMail($notificacion),
            $usuario,
            $notificacion->dependencia_id,
            $forzar ? null : 'notificacion:'.$notificacion->id,
            ['notificacion_id' => $notificacion->id, 'referencia' => $notificacion->referencia_tipo.'#'.$notificacion->referencia_id],
        );
    }

    /**
     * @return array{0: string, 1: Usuario|null}|null
     */
    private function destinatarioDependencia(Dependencia $dependencia): ?array
    {
        $admin = $dependencia->usuarios()
            ->where('estado', true)
            ->whereIn('rol', [Usuario::ROLE_ADMIN_TEC, Usuario::ROLE_ADMIN_COM])
            ->orderByRaw('CASE WHEN rol = ? THEN 0 ELSE 1 END', [Usuario::ROLE_ADMIN_COM])
            ->orderBy('id')
            ->first();

        if ($admin instanceof Usuario && $admin->email) {
            return [$admin->email, $admin];
        }

        if ($dependencia->correo_contacto) {
            return [$dependencia->correo_contacto, null];
        }

        return null;
    }

    private function enviar(
        string $tipo,
        string $destinatario,
        string $asunto,
        Mailable $mailable,
        ?Usuario $usuario = null,
        ?int $dependenciaId = null,
        ?string $clave = null,
        array $metadata = [],
    ): ?CorreoLog {
        if (! $this->habilitado($tipo)) {
            return null;
        }

        if (! filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $payload = [
            'usuario_id' => $usuario?->id,
            'dependencia_id' => $dependenciaId,
            'tipo' => $tipo,
            'destinatario' => $destinatario,
            'asunto' => mb_substr($asunto, 0, 200),
            'estado' => 'pendiente',
            'error' => null,
            'metadata' => $metadata,
        ];

        $log = $clave !== null
            ? CorreoLog::firstOrNew(['clave' => $clave])
            : new CorreoLog;

        if ($log->exists && $log->estado === 'enviado') {
            return $log;
        }

        try {
            $log->fill($payload)->save();
        } catch (QueryException $e) {
            // Otra petición registró la misma clave al mismo tiempo: reutilizarla.
            $log = $clave !== null ? CorreoLog::where('clave', $clave)->first() : null;

            if ($log === null) {
                throw $e;
            }

            if ($log->estado === 'enviado') {
                return $log;
            }

            $log->fill($payload)->save();
        }

        // Con el transporte "log" (MAIL_MAILER=log) nada sale al exterior. Se
        // registra como fallido para que la deduplicación no bloquee un envío
        // real cuando se configure el SMTP.
        if (config('mail.default') === 'log') {
            $log->update([
                'estado' => 'fallido',
                'error' => 'Transporte de correo en modo log: el mensaje no se envió realmente.',
            ]);

            return $log;
        }

        try {
            Mail::to($destinatario)->send($mailable);

            $log->update([
                'estado' => 'enviado',
                'enviado_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar correo automático', [
                'tipo' => $tipo,
                'destinatario' => $destinatario,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'estado' => 'fallido',
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ]);
        }

        return $log;
    }
}
