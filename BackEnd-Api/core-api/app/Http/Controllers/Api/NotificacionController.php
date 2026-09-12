<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NotificacionMail;
use App\Models\Notificacion;
use App\Models\Usuario;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class NotificacionController extends Controller
{
    use EnsuresDependencia;

    public function index(Request $request): JsonResponse
    {
        $q = Notificacion::query();

        if ($request->filled('estatus')) {
            $q->where('estatus', $request->string('estatus'));
        }
        if ($request->filled('referencia_tipo')) {
            $q->where('referencia_tipo', $request->string('referencia_tipo'));
        }
        if ($request->boolean('no_leidas')) {
            $q->whereNull('leida_at');
        }

        return response()->json($q->orderByDesc('created_at')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo' => 'required|string|max:50',
            'titulo' => 'nullable|string|max:200',
            'referencia_tipo' => 'required|in:ticket,servicio,reporte',
            'referencia_id' => 'required|integer',
            'destinatario' => 'nullable|email',
            'usuario_id' => 'nullable|integer',
            'asunto' => 'required|string|max:200',
            'cuerpo' => 'nullable|string|max:5000',
        ]);

        if (! empty($data['usuario_id'])) {
            $valido = $this->validatedDependenciaId($request, 'usuario_id', Usuario::class);
            if ($valido === null) {
                return response()->json(['message' => 'El usuario destinatario no existe en tu dependencia'], 422);
            }
        }

        // La referencia debe existir en la dependencia (no filtrar datos de otros tenants)
        $mapa = [
            'ticket' => \App\Models\Ticket::class,
            'servicio' => \App\Models\Servicio::class,
            'reporte' => \App\Models\Reporte::class,
        ];
        $modelo = $mapa[$data['referencia_tipo']];
        if ($modelo::find((int) $data['referencia_id']) === null) {
            return response()->json(['message' => "La referencia {$data['referencia_tipo']} no existe en tu dependencia"], 422);
        }

        if (empty($data['destinatario']) && ! empty($data['usuario_id'])) {
            $data['destinatario'] = Usuario::find((int) $data['usuario_id'])?->email;
        }

        $notif = Notificacion::create([
            ...$data,
            'estatus' => 'pendiente',
            'created_at' => now(),
        ]);

        if ($notif->destinatario) {
            try {
                Mail::to($notif->destinatario)->send(new NotificacionMail($notif));
                $notif->update(['estatus' => 'enviado', 'enviado_at' => now()]);
            } catch (\Throwable $e) {
                report($e);
                $notif->update(['estatus' => 'fallido']);
            }
        }

        return response()->json($notif, 201);
    }

    public function markRead(Request $request, Notificacion $notificacion): JsonResponse
    {
        $this->ensureOwnDependencia($notificacion);

        $notificacion->update(['leida_at' => now()]);

        return response()->json($notificacion);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notificacion::whereNull('leida_at')->update(['leida_at' => now()]);

        return response()->json(['message' => 'Todas marcadas como leídas']);
    }

    public function resend(Request $request, Notificacion $notificacion): JsonResponse
    {
        $this->ensureOwnDependencia($notificacion);

        if (! $notificacion->destinatario) {
            return response()->json(['message' => 'La notificación no tiene destinatario'], 422);
        }

        Mail::to($notificacion->destinatario)->send(new NotificacionMail($notificacion));
        $notificacion->update(['estatus' => 'enviado', 'enviado_at' => now()]);

        return response()->json($notificacion);
    }
}
