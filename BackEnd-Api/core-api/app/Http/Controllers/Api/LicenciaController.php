<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Suscripcion;
use App\Services\LicenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $user = $request->user();
        $dependencia = $user->dependencia;

        if (! $dependencia) {
            return response()->json(['success' => false, 'message' => 'Dependencia no encontrada'], 404);
        }

        $licencia = $this->licencias->resolverLicencia($licenciaId);

        if ($licencia === null) {
            return response()->json(['success' => false, 'message' => 'Licencia no encontrada'], 404);
        }

        $precio = ($licencia->tipo === 'anual' && $licencia->precio_anual)
            ? (float) $licencia->precio_anual
            : (float) $licencia->precio;

        // Trial: activación directa sin pasarela
        if ($precio == 0 || $licencia->tipo === 'trial') {
            $this->licencias->activarLicencia($dependencia, $licencia, 'trial', 0);

            return response()->json(['success' => true, 'message' => 'Licencia trial activada', 'pasarela' => 'none']);
        }

        try {
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', '')), '/');

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
                'external_reference' => 'CS-'.$licencia->id.'-'.$dependencia->id,
                'notification_url' => rtrim(config('app.url'), '/').'/api/v1/mercadopago/licencia-webhook',
                'statement_descriptor' => 'CsRecords',
                'metadata' => [
                    'dependencia_id' => $dependencia->id,
                    'licencia_id' => (int) $licencia->id,
                    'tipo' => $licencia->tipo,
                ],
            ]);

            Suscripcion::updateOrCreate(
                ['dependencia_id' => $dependencia->id],
                ['mercadopago_preference_id' => $preference->id]
            );

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

    /**
     * Webhook de Mercado Pago.
     * Seguridad: verifica la firma x-signature según la doc oficial de MP
     * cuando está configurado el secret; ignora referencias desconocidas.
     */
    public function webhookMercadoPago(Request $request): JsonResponse
    {
        try {
            // 1) Verificación de firma (HMAC SHA256 sobre id;x-request-id;dataId)
            $secret = config('services.mercadopago.webhook_secret');
            if ($secret && ! $this->firmaValida($request, $secret)) {
                Log::warning('Webhook MP con firma inválida', ['ip' => $request->ip()]);

                return response()->json(['status' => 'ok'], 200); // no filtrar el motivo
            }

            $payload = $request->all();
            $tipo = $payload['type'] ?? $payload['topic'] ?? null;
            $paymentId = $payload['data']['id'] ?? null;

            if ($tipo !== 'payment' || empty($paymentId)) {
                return response()->json(['status' => 'ok'], 200);
            }

            $payment = (new PaymentClient)->get((string) $paymentId);

            if (! $payment || $payment->status !== 'approved') {
                return response()->json(['status' => 'ok'], 200);
            }

            $reference = $payment->external_reference ?? '';
            if (! str_starts_with($reference, 'CS-')) {
                Log::warning('Referencia MP desconocida', ['ref' => $reference]);

                return response()->json(['status' => 'ok'], 200);
            }

            $parts = explode('-', $reference);
            if (count($parts) !== 3) {
                Log::error('Formato de referencia inválido', ['ref' => $reference]);

                return response()->json(['status' => 'ok'], 200);
            }

            [, $licenciaId, $dependenciaId] = $parts;

            $dependencia = Dependencia::find((int) $dependenciaId);
            if (! $dependencia) {
                Log::error('Webhook MP: dependencia inexistente', ['id' => $dependenciaId]);

                return response()->json(['status' => 'ok'], 200);
            }

            // Idempotencia: si ya procesamos ese pago, no volver a activar
            $existente = Suscripcion::where('dependencia_id', $dependencia->id)->first();
            if ($existente && $existente->mercadopago_payment_id == $paymentId && $existente->estado === 'activa') {
                return response()->json(['status' => 'ok'], 200);
            }

            $licencia = $this->licencias->resolverLicencia((int) $licenciaId);
            if ($licencia === null) {
                Log::error('Webhook MP: licencia inexistente', ['id' => $licenciaId]);

                return response()->json(['status' => 'ok'], 200);
            }

            $this->licencias->activarLicencia(
                $dependencia,
                $licencia,
                'mercadopago',
                (float) ($payment->transaction_amount ?? 0),
                (string) $paymentId
            );

            return response()->json(['status' => 'ok'], 200);
        } catch (\Throwable $e) {
            Log::error('Error en webhook MP', ['error' => $e->getMessage()]);

            // 200 para evitar reintentos infinitos; el error queda registrado
            return response()->json(['status' => 'ok'], 200);
        }
    }

    /** Consulta del estado de un pago (polling del front al volver de MP). */
    public function verificarPagoMercadoPago(Request $request, string $paymentId): JsonResponse
    {
        try {
            $payment = (new PaymentClient)->get($paymentId);

            if (! $payment) {
                return response()->json(['success' => false, 'message' => 'Pago no encontrado'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'status_detail' => $payment->status_detail ?? '',
                    'amount' => $payment->transaction_amount,
                    'approved' => $payment->status === 'approved',
                    'external_reference' => $payment->external_reference ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error verificando pago MP', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Error al verificar el pago'], 502);
        }
    }

    /**
     * Validación de x-signature de Mercado Pago.
     * Manifest: "id:{dataId};" o "id:{dataId};x-request-id:{xhrId};" según versión.
     */
    private function firmaValida(Request $request, string $secret): bool
    {
        $signature = $request->header('x-signature');
        $dataId = (string) ($request->query('data.id') ?? $request->input('data.id', ''));
        $requestId = (string) $request->header('x-request-id', '');

        if (! $signature || $dataId === '') {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};";
        $esperada = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($esperada, strtolower(explode(',', $signature)[0] ?? ''));
    }
}
