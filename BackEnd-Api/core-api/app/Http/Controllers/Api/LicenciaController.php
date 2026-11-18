<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\PagoLicencia;
use App\Services\LicenciaService;
use App\Support\PagoReferencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class LicenciaController extends Controller
{
    public function __construct(private LicenciaService $licencias)
    {
        if (class_exists(MercadoPagoConfig::class) && config('services.mercadopago.token')) {
            MercadoPagoConfig::setAccessToken(config('services.mercadopago.token'));
        }
    }

    /** Catálogo público de planes. */
    public function disponibles(): JsonResponse
    {
        $licencias = \App\Models\Licencia::where('activo', true)
            ->orderBy('precio')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'nombre' => $l->nombre,
                'codigo' => $l->codigo,
                'tipo' => $l->tipo,
                'max_usuarios' => $l->max_usuarios,
                'max_reportes_mensuales' => $l->max_reportes_mensuales,
                'precio' => $l->precio,
                'precio_anual' => $l->precio_anual,
                'dias_prueba' => $l->dias_prueba,
                'descripcion' => $l->descripcion,
                'origen' => 'db',
            ]);

        if ($licencias->isEmpty()) {
            $licencias = collect($this->licencias->catalogoFallback())
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'nombre' => $l->nombre,
                    'codigo' => $l->codigo,
                    'tipo' => $l->tipo,
                    'max_usuarios' => $l->max_usuarios,
                    'max_reportes_mensuales' => $l->max_reportes_mensuales,
                    'precio' => $l->precio,
                    'precio_anual' => $l->precio_anual,
                    'dias_prueba' => $l->dias_prueba,
                    'descripcion' => $l->descripcion,
                    'origen' => 'fallback',
                ]);
        }

        return response()->json(['success' => true, 'data' => $licencias]);
    }

    public function comprarLicenciaMercadoPago(Request $request, int $licenciaId): JsonResponse
    {
        $user = $this->usuario($request);
        $dependencia = $user->dependencia;

        if (! $dependencia instanceof Dependencia) {
            return response()->json(['success' => false, 'message' => 'Dependencia no encontrada'], 404);
        }

        $licencia = $this->licencias->resolverLicencia($licenciaId);

        if ($licencia === null) {
            return response()->json(['success' => false, 'message' => 'Licencia no encontrada'], 404);
        }

        $precio = ($licencia->tipo === 'anual' && $licencia->precio_anual)
            ? (float) $licencia->precio_anual
            : (float) $licencia->precio;

        // Trial: activación directa sin pasarela, pero solo si la dependencia
        // no consumió ya su periodo de prueba.
        if ($precio == 0 || $licencia->tipo === 'trial') {
            if (! $this->licencias->puedeActivarTrial($dependencia)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El periodo de prueba ya fue utilizado. Elige un plan de pago.',
                ], 422);
            }

            $this->licencias->activarLicencia($dependencia, $licencia, 'trial', 0);

            return response()->json(['success' => true, 'message' => 'Licencia trial activada', 'pasarela' => 'none']);
        }

        try {
            $frontendUrl = rtrim((string) (config('services.frontend.url') ?: config('app.url')), '/');
            $referencia = 'CS-'.$licencia->id.'-'.$dependencia->id;

            $preference = (new PreferenceClient)->create([
                'items' => [[
                    'id' => (string) $licencia->id,
                    'title' => $licencia->nombre.' — Licencia '.ucfirst($licencia->tipo),
                    'description' => 'Licencia CsRecords - '.$licencia->nombre,
                    'quantity' => 1,
                    'unit_price' => $precio,
                    'currency_id' => 'MXN',
                ]],
                'payer' => [
                    'name' => $user->nombre,
                    'email' => $user->email,
                ],
                'back_urls' => [
                    'success' => $frontendUrl.'/licencias/exito',
                    'failure' => $frontendUrl.'/licencias/error',
                    'pending' => $frontendUrl.'/licencias/pendiente',
                ],
                'auto_return' => 'approved',
                'external_reference' => $referencia,
                'notification_url' => rtrim(config('app.url'), '/').'/api/v1/mercadopago/licencia-webhook',
                'statement_descriptor' => 'CsRecords',
                'metadata' => [
                    'dependencia_id' => $dependencia->id,
                    'licencia_id' => (int) $licencia->id,
                    'tipo' => $licencia->tipo,
                ],
            ]);

            PagoLicencia::create([
                'dependencia_id' => $dependencia->id,
                'licencia_id' => $licencia->exists ? $licencia->id : null,
                'preferencia_id' => $preference->id,
                'external_reference' => $referencia,
                'estado' => 'pendiente',
                'metodo' => 'mercadopago',
                'monto' => $precio,
                'moneda' => 'MXN',
            ]);

            return response()->json([
                'success' => true,
                'init_point' => $preference->init_point,
                'preference_id' => $preference->id,
                'pasarela' => 'mercadopago',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error creando preferencia Mercado Pago', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Error al iniciar el pago. Intenta de nuevo.'], 502);
        }
    }

    public function webhookMercadoPago(Request $request): JsonResponse
    {
        try {
            $secret = config('services.mercadopago.webhook_secret');
            if ($secret && ! $this->firmaValida($request, $secret)) {
                Log::warning('Webhook MP con firma inválida', ['ip' => $request->ip()]);

                return response()->json(['status' => 'ok'], 200);
            }

            $payload = $request->all();
            $tipo = $payload['type'] ?? $payload['topic'] ?? $request->query('type') ?? $request->query('topic');
            $paymentId = $payload['data']['id'] ?? $request->query('data.id') ?? $request->query('id');

            if ($tipo !== 'payment' || empty($paymentId)) {
                return response()->json(['status' => 'ok'], 200);
            }

            $payment = (new PaymentClient)->get((int) $paymentId);

            if ($payment->status === 'approved') {
                $this->procesarPagoAprobado($payment, (string) $paymentId);
            } else {
                PagoLicencia::where('payment_id', (string) $paymentId)
                    ->update(['estado' => in_array($payment->status, ['rejected', 'cancelled'], true) ? $payment->status : 'pendiente']);
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (\Throwable $e) {
            Log::error('Error en webhook MP', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'ok'], 200);
        }
    }

    public function verificarPagoMercadoPago(Request $request, string $paymentId): JsonResponse
    {
        try {
            $dependenciaId = (int) $this->usuario($request)->dependencia_id;

            $payment = (new PaymentClient)->get((int) $paymentId);

            if (! PagoReferencia::perteneceA($payment->external_reference ?? null, $dependenciaId)) {
                return response()->json(['success' => false, 'message' => 'Pago no encontrado'], 404);
            }

            if ($payment->status === 'approved') {
                $this->procesarPagoAprobado($payment, (string) $paymentId);
            }

            $dependencia = Dependencia::find($dependenciaId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'status_detail' => $payment->status_detail ?? '',
                    'amount' => $payment->transaction_amount,
                    'approved' => $payment->status === 'approved',
                    'external_reference' => $payment->external_reference ?? null,
                    'licencia' => $dependencia ? [
                        'tipo_licencia' => $dependencia->tipo_licencia,
                        'fecha_expiracion' => $dependencia->fecha_expiracion?->format('Y-m-d'),
                        'activa' => $dependencia->activa,
                    ] : null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error verificando pago MP', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Error al verificar el pago'], 502);
        }
    }

    private function procesarPagoAprobado(object $payment, string $paymentId): void
    {
        $referencia = PagoReferencia::parsear($payment->external_reference ?? null);

        if ($referencia === null) {
            Log::warning('Referencia MP desconocida', ['ref' => $payment->external_reference ?? '']);

            return;
        }

        $dependencia = Dependencia::find($referencia['dependencia']);
        $licencia = $this->licencias->resolverLicencia($referencia['licencia']);

        if (! $dependencia || ! $licencia) {
            Log::error('Webhook MP: referencia inexistente', $referencia);

            return;
        }

        $pago = PagoLicencia::firstOrNew(['payment_id' => $paymentId]);

        if ($pago->exists && $pago->procesado_en !== null) {
            return;
        }

        if (! $pago->exists) {
            $pendiente = PagoLicencia::where('external_reference', (string) $payment->external_reference)
                ->whereNull('payment_id')
                ->latest()
                ->first();

            if ($pendiente) {
                $pago = $pendiente;
                $pago->payment_id = $paymentId;
            }
        }

        $pago->fill([
            'dependencia_id' => $dependencia->id,
            'licencia_id' => $licencia->exists ? $licencia->id : $pago->licencia_id,
            'external_reference' => (string) $payment->external_reference,
            'estado' => 'aprobado',
            'metodo' => 'mercadopago',
            'monto' => (float) ($payment->transaction_amount ?? 0),
            'moneda' => 'MXN',
            'pagado_en' => now(),
        ])->save();

        DB::transaction(function () use ($pago, $dependencia, $licencia, $payment, $paymentId) {
            $bloqueado = PagoLicencia::whereKey($pago->id)->lockForUpdate()->first();

            if (! $bloqueado || $bloqueado->procesado_en !== null) {
                return;
            }

            $this->licencias->activarLicencia(
                $dependencia,
                $licencia,
                'mercadopago',
                (float) ($payment->transaction_amount ?? 0),
                $paymentId
            );

            $bloqueado->procesado_en = now();
            $bloqueado->save();
        });
    }

    private function firmaValida(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('x-signature', '');

        if ($signature === '') {
            return false;
        }

        $partes = [];
        foreach (explode(',', $signature) as $parte) {
            $segmento = explode('=', trim($parte), 2);
            if (count($segmento) === 2) {
                $partes[$segmento[0]] = $segmento[1];
            }
        }

        $ts = $partes['ts'] ?? '';
        $v1 = $partes['v1'] ?? '';
        $dataId = (string) ($request->query('data.id') ?? $request->input('data.id', ''));
        $requestId = (string) $request->header('x-request-id', '');

        if ($ts === '' || $v1 === '' || $dataId === '') {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $esperada = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($esperada, strtolower($v1));
    }
}
