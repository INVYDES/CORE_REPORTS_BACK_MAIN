<?php

namespace App\Services;

use App\Models\Usuario;
use App\Models\VerificacionCorreoToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VerificacionCorreoService
{
    public function __construct(private CorreoAutomatizadoService $correos)
    {
    }

    /**
     * Genera un código y un enlace mágico. Si se respeta la espera, evita
     * reenviar correos antes del cooldown configurado.
     *
     * @return array{
     *     enviado: bool,
     *     motivo?: string,
     *     expira?: \Illuminate\Support\Carbon,
     *     reenviar_en_segundos?: int,
     *     correo?: \App\Models\CorreoLog|null
     * }
     */
    public function solicitar(Usuario $usuario, Request $request, bool $respetarEspera = false): array
    {
        $cooldown = max(10, (int) config('correo.reenvio_segundos', 60));

        $ultimo = $usuario->verificacionesCorreo()
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($respetarEspera && $ultimo && $ultimo->expires_at->isFuture()) {
            $transcurridos = (int) $ultimo->created_at->diffInSeconds(now());
            $restantes = $cooldown - $transcurridos;

            if ($restantes > 0) {
                return [
                    'enviado' => false,
                    'motivo' => 'espera',
                    'expira' => $ultimo->expires_at,
                    'reenviar_en_segundos' => $restantes,
                ];
            }
        }

        $usuario->verificacionesCorreo()
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $tokenPlano = Str::random(64);
        $expira = now()->addMinutes(max(5, (int) config('correo.codigo_expiracion_minutos', 20)));

        VerificacionCorreoToken::create([
            'usuario_id' => $usuario->id,
            'token_hash' => hash('sha256', $tokenPlano),
            'code_hash' => Hash::make($codigo),
            'sent_to' => $usuario->email,
            'expires_at' => $expira,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);

        $enlace = $this->enlaceVerificacion($tokenPlano, $usuario->email);
        $log = $this->correos->enviarVerificacion($usuario, $codigo, $enlace, $expira);
        $enviado = $log?->estado === 'enviado';

        return [
            'enviado' => $enviado,
            'motivo' => $enviado ? null : 'error_envio',
            'expira' => $expira,
            'reenviar_en_segundos' => $cooldown,
            'correo' => $log,
        ];
    }

    /**
     * @return array{usuario?: Usuario, error?: string, intentos_restantes?: int}
     */
    public function verificarCodigo(string $email, string $codigo): array
    {
        $usuario = Usuario::where('email', trim($email))->first();

        if (! $usuario) {
            return ['error' => 'codigo'];
        }

        if ($usuario->email_verified_at !== null) {
            return ['usuario' => $usuario];
        }

        $registro = $usuario->verificacionesCorreo()
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $registro) {
            return ['error' => 'expirado'];
        }

        $maxIntentos = max(1, (int) config('correo.max_intentos_codigo', 5));

        if ($registro->attempts >= $maxIntentos) {
            return ['error' => 'intentos'];
        }

        if (! Hash::check($codigo, $registro->code_hash)) {
            $nuevos = $registro->attempts + 1;
            $registro->update(['attempts' => $nuevos]);

            return [
                'error' => 'codigo',
                'intentos_restantes' => max(0, $maxIntentos - $nuevos),
            ];
        }

        return ['usuario' => $this->marcarVerificado($usuario, $registro)];
    }

    public function verificarToken(string $tokenPlano): ?Usuario
    {
        $registro = VerificacionCorreoToken::query()
            ->where('token_hash', hash('sha256', $tokenPlano))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $registro) {
            return null;
        }

        $usuario = $registro->usuario;

        if (! $usuario || ! $usuario->estado) {
            return null;
        }

        return $this->marcarVerificado($usuario, $registro);
    }

    private function marcarVerificado(Usuario $usuario, VerificacionCorreoToken $registro): Usuario
    {
        $usuario->forceFill(['email_verified_at' => now()])->save();

        $registro->update(['consumed_at' => now()]);

        $usuario->verificacionesCorreo()
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        return $usuario;
    }

    private function enlaceVerificacion(string $token, string $email): string
    {
        return rtrim((string) config('correo.frontend_url'), '/')
            .'/verificar-correo?token='.urlencode($token)
            .'&email='.urlencode($email);
    }
}
