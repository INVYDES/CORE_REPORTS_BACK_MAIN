<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\Reporte;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\CorreoAutomatizadoService;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class NotificacionController extends Controller
{
    use EnsuresDependencia;

    public function __construct(private CorreoAutomatizadoService $correos)
    {
    }

    private function authUser(Request $request): ?Usuario
    {
        $user = $request->user() ?? auth('sanctum')->user() ?? auth()->user();

        return $user instanceof Usuario ? $user : null;
    }

    public function index(Request $request): JsonResponse
    {
        $q = Notificacion::query();
        $user = $this->authUser($request);

        if ($request->filled('estatus')) {
            $q->where('estatus', $request->string('estatus'));
        }
        if ($request->filled('referencia_tipo')) {
            $q->where('referencia_tipo', $request->string('referencia_tipo'));
        }
        $esTecnico = $user && $user->rol === 3;
        if ($request->filled('usuario_id') && ! $esTecnico) {
            $q->where('usuario_id', (int) $request->get('usuario_id'));
        } elseif ($esTecnico) {
            // Técnicos solo ven notificaciones dirigidas a ellos (por usuario_id o por email de destinatario) o broadcast
            $q->where(function ($sub) use ($user) {
                $sub->where('usuario_id', $user->id)
                    ->orWhere('destinatario', $user->email)
                    ->orWhere(function ($b) {
                        $b->whereNull('usuario_id')->whereNull('destinatario');
                    });
            });
        }
        if ($request->filled('area_id')) {
            $areaId = (int) $request->get('area_id');

            $q->where(function ($where) use ($areaId) {
                $where
                    ->where(function ($sub) use ($areaId) {
                        $sub->where('referencia_tipo', 'ticket')
                            ->whereIn('referencia_id', Ticket::query()->select('id')->where('area_id', $areaId));
                    })
                    ->orWhere(function ($sub) use ($areaId) {
                        $sub->where('referencia_tipo', 'servicio')
                            ->whereIn('referencia_id', Servicio::query()->select('id')->where('area_id', $areaId));
                    })
                    ->orWhere(function ($sub) use ($areaId) {
                        $sub->where('referencia_tipo', 'reporte')
                            ->whereIn('referencia_id', Reporte::query()->select('id')->where('area_id', $areaId));
                    });
            });
        }
        if ($request->boolean('no_leidas')) {
            $q->whereNull('leida_at');
        }

        $noLeidas = (clone $q)->whereNull('leida_at')->count();

        $paginator = $q->orderByDesc('created_at')->paginate(min(max((int) $request->get('per_page', 15), 1), 100));
        $this->attachReferenciaContext($paginator->getCollection());

        $payload = $paginator->toArray();
        $payload['no_leidas'] = $noLeidas;
        $payload['meta']['no_leidas'] = $noLeidas;

        return response()->json($payload);
    }

    private function attachReferenciaContext(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $ids = $items->groupBy('referencia_tipo')->map(
            fn (Collection $rows) => $rows->pluck('referencia_id')->unique()->values()->all()
        );

        $modelos = [
            'ticket' => Ticket::class,
            'servicio' => Servicio::class,
            'reporte' => Reporte::class,
        ];

        $entidades = [];
        foreach ($modelos as $tipo => $modelo) {
            if (empty($ids[$tipo])) {
                continue;
            }

            $entidades[$tipo] = $modelo::query()
                ->with('area:id,nombre,codigo')
                ->whereIn('id', $ids[$tipo])
                ->get()
                ->keyBy('id');
        }

        foreach ($items as $notificacion) {
            $entidad = $entidades[$notificacion->referencia_tipo][$notificacion->referencia_id] ?? null;
            $area = $entidad?->area;

            $notificacion->setAttribute('area_id', $entidad?->area_id);
            $notificacion->setAttribute('area', $area ? [
                'id' => $area->id,
                'nombre' => $area->nombre,
                'codigo' => $area->codigo,
            ] : null);
            $notificacion->setAttribute('referencia', $entidad ? [
                'id' => $entidad->id,
                'folio' => $entidad->folio ?? null,
                'asunto' => $entidad->asunto ?? null,
                'estatus' => $entidad->estatus ?? null,
                'area_id' => $entidad->area_id,
            ] : null);
        }
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

        // El destinatario debe ser un usuario de la propia dependencia: el
        // endpoint no puede usarse para enviar correos a direcciones arbitrarias.
        if (! empty($data['destinatario'])) {
            $depId = $this->usuarioActual()->dependencia_id;
            $destinatarioValido = Usuario::where('dependencia_id', $depId)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $data['destinatario']))])
                ->when(! empty($data['usuario_id']), fn ($q) => $q->whereKey((int) $data['usuario_id']))
                ->exists();

            if (! $destinatarioValido) {
                return response()->json(['message' => 'El destinatario debe ser un usuario de tu dependencia'], 422);
            }
        }

        $notif = Notificacion::create([
            ...$data,
            'estatus' => 'pendiente',
            'created_at' => now(),
        ]);

        if ($notif->destinatario && $this->correos->habilitado('notificacion')) {
            $log = $this->correos->enviarNotificacion($notif);

            if ($log?->estado === 'enviado') {
                $notif->update(['estatus' => 'enviado', 'enviado_at' => now()]);
            } else {
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
        $user = $this->authUser($request);
        $q = Notificacion::whereNull('leida_at');
        if ($user && $user->rol === 3) {
            $q->where(function ($sub) use ($user) {
                $sub->where('usuario_id', $user->id)
                    ->orWhere('destinatario', $user->email);
            });
        }
        $q->update(['leida_at' => now()]);

        return response()->json(['message' => 'Todas marcadas como leídas']);
    }

    public function resend(Request $request, Notificacion $notificacion): JsonResponse
    {
        $this->ensureOwnDependencia($notificacion);

        if (! $notificacion->destinatario) {
            return response()->json(['message' => 'La notificación no tiene destinatario'], 422);
        }

        if (! $this->correos->habilitado('notificacion')) {
            return response()->json(['message' => 'El envío de notificaciones por correo está deshabilitado.'], 422);
        }

        $log = $this->correos->enviarNotificacion($notificacion, true);

        if ($log?->estado === 'enviado') {
            $notificacion->update(['estatus' => 'enviado', 'enviado_at' => now()]);

            return response()->json($notificacion);
        }

        return response()->json([
            'message' => 'No se pudo reenviar la notificación. Revisa la configuración de correo.',
        ], 502);
    }
}
