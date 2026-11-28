<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Equipo;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    private function authUser(Request $request): Usuario
    {
        $user = $request->user() ?? auth()->user();
        if (! $user instanceof Usuario) {
            abort(401, 'No autenticado.');
        }

        return $user;
    }

    private function ensureOwnDependencia(Request $request, Model $model): void
    {
        if ((int) $model->getAttribute('dependencia_id') !== (int) $this->authUser($request)->dependencia_id) {
            abort(403, 'El recurso no pertenece a tu dependencia.');
        }
    }

    private function resolveFk(Request $request, string $field, string $modelClass): ?int
    {
        $id = $request->input($field);
        if ($id === null || $id === '') {
            return null;
        }

        $depId = $this->authUser($request)->dependencia_id;
        $found = $modelClass::withoutGlobalScopes()
            ->where('dependencia_id', $depId)
            ->find((int) $id);

        if ($found === null) {
            return null;
        }

        return (int) $found->getKey();
    }

    private function generarFolioSecuencial(int $dependenciaId): string
    {
        // Bloquea la fila de la empresa (dentro de la transacción del alta) para
        // que dos altas simultáneas no calculen el mismo consecutivo.
        \App\Models\Dependencia::withoutGlobalScopes()->whereKey($dependenciaId)->lockForUpdate()->first();

        $ahora = now('America/Mexico_City');
        $prefijo = 'TKT-'.$ahora->format('ymd');
        $prefijoLegacy = 'TKT-'.$ahora->format('Ymd');

        $ultimos = Ticket::withoutGlobalScopes()
            ->where('dependencia_id', $dependenciaId)
            ->where(function ($q) use ($prefijo, $prefijoLegacy) {
                $q->where('folio', 'like', $prefijo.'-%')
                    ->orWhere('folio', 'like', $prefijoLegacy.'-%');
            })
            ->pluck('folio');

        $maxSecuencia = 0;
        foreach ($ultimos as $f) {
            $num = (int) substr((string) $f, -4);
            if ($num > $maxSecuencia) {
                $maxSecuencia = $num;
            }
        }

        return sprintf('%s-%04d', $prefijo, $maxSecuencia + 1);
    }

    private function sincronizarTicketsConReportesFinalizados(int $depId): void
    {
        try {
            $ahora = now('America/Mexico_City')->format('Y-m-d H:i:s');
            DB::table('tickets')
                ->where('dependencia_id', $depId)
                ->where('folio', 'like', 'TKT-20%')
                ->update([
                    'folio' => DB::raw("CONCAT('TKT-', SUBSTRING(folio, 7))"),
                ]);
            DB::table('tickets')
                ->where('dependencia_id', $depId)
                ->whereIn('estatus', ['abierto', 'en_proceso'])
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('reportes')
                        ->whereColumn('reportes.ticket_id', 'tickets.id')
                        ->where('reportes.estatus', 'finalizado')
                        ->whereNull('reportes.deleted_at');
                })
                ->update([
                    'estatus' => 'resuelto',
                    'fecha_atencion' => DB::raw("COALESCE(fecha_atencion, '{$ahora}')"),
                    'updated_at' => $ahora,
                ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth()->user();
        // La sincronización hace UPDATEs: solo en la primera página, no en cada
        // página cuando el front recorre el listado completo.
        if ($user && isset($user->dependencia_id) && (int) $request->get('page', 1) <= 1) {
            $this->sincronizarTicketsConReportesFinalizados((int) $user->dependencia_id);
        }

        $q = Ticket::with(['asignado', 'area', 'equipo']);

        if ($user && (int) $user->rol === 3) {
            $q->where('usuario_asignado_id', $user->id);
        }

        if ($request->filled('estatus')) {
            $q->where('estatus', $request->string('estatus'));
        }
        if ($request->filled('prioridad')) {
            $q->where('prioridad', $request->string('prioridad'));
        }
        if ($request->filled('area_id')) {
            $q->where('area_id', (int) $request->area_id);
        }
        if ($request->filled('equipo_id')) {
            $q->where('equipo_id', (int) $request->equipo_id);
        }
        if ($request->boolean('sin_asignar')) {
            $q->whereNull('usuario_asignado_id');
        }
        if ($request->filled('usuario_asignado_id')) {
            $q->where('usuario_asignado_id', (int) $request->usuario_asignado_id);
        }
        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(fn ($qq) => $qq
                ->where('folio', 'like', "%{$s}%")
                ->orWhere('asunto', 'like', "%{$s}%"));
        }

        // id como desempate: con fechas repetidas el orden no es estable y la
        // paginación por offset repetía u omitía registros entre páginas.
        return response()->json($q->orderByDesc('fecha_solicitud')->orderByDesc('id')->paginate(min(max((int) $request->get('per_page', 15), 1), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folio' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:~^[A-Za-z0-9][A-Za-z0-9._/-]*$~'],
            'asunto' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'solicitante' => 'nullable|string|max:150',
            'cargo' => 'nullable|string|max:150',
            'prioridad' => 'sometimes|in:alta,media,baja',
            'estatus' => 'sometimes|in:abierto,en_proceso,resuelto,cerrado',
            'fecha_limite' => 'nullable|date',
            'sla_horas' => 'nullable|integer|min:1',
            'usuario_asignado_id' => 'nullable|integer',
            'area_id' => 'nullable|integer',
            'equipo_id' => 'nullable|integer',
            'evidencias' => 'nullable|array|max:5',
            'evidencias.*' => 'nullable|string',
        ]);

        $user = $this->authUser($request);
        $depId = (int) $user->dependencia_id;

        $urlsEvidencias = [];
        $evidenciasInput = (array) ($request->input('evidencias', []));
        foreach ($evidenciasInput as $b64) {
            if (! is_string($b64)) {
                continue;
            }
            if (preg_match('#^data:image/(jpeg|jpg|png|webp);base64,(.+)$#is', $b64, $m)) {
                $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                $bytes = base64_decode($m[2], true);
                if ($bytes !== false) {
                    $path = 'evidencias_tickets/'.$depId.'/tkt_'.uniqid('int_', true).'.'.$ext;
                    try {
                        Storage::disk('public')->put($path, $bytes);
                        $urlsEvidencias[] = Storage::disk('public')->url($path);
                    } catch (\Throwable $e) {
                        Log::warning('No se pudo guardar imagen de evidencia de ticket: '.$e->getMessage());
                    }
                }
            }
        }

        unset($data['evidencias']);
        if (! empty($urlsEvidencias)) {
            $descBase = trim((string) ($data['descripcion'] ?? ''));
            $descBase .= "\n\nEvidencias Fotográficas Adjuntas:\n";
            foreach ($urlsEvidencias as $num => $url) {
                $n = $num + 1;
                $descBase .= "- Foto {$n}: {$url}\n";
            }
            $data['descripcion'] = trim($descBase);
        }

        $data['dependencia_id'] = $depId;
        $data['usuario_asignado_id'] = $this->resolveFk($request, 'usuario_asignado_id', Usuario::class);
        $data['area_id'] = $this->resolveFk($request, 'area_id', Area::class);
        $data['equipo_id'] = $this->resolveFk($request, 'equipo_id', Equipo::class);

        if ($data['usuario_asignado_id'] !== null) {
            $this->authorize('assign', Ticket::class);
        }

        $data['estatus'] = $data['estatus'] ?? 'abierto';
        $data['prioridad'] = $data['prioridad'] ?? 'media';
        $data['fecha_solicitud'] = now('America/Mexico_City')->format('Y-m-d H:i:s');

        $ticket = DB::transaction(function () use ($data, $depId) {
            $folioActual = trim((string) ($data['folio'] ?? ''));
            if ($folioActual === '' || preg_match('/^TKT-\d{6}$/', $folioActual)) {
                $data['folio'] = $this->generarFolioSecuencial($depId);
            }

            $tkt = new Ticket;
            $tkt->forceFill($data);
            $tkt->save();

            return $tkt;
        });

        return response()->json($ticket->load(['asignado', 'area', 'equipo']), 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->ensureOwnDependencia($request, $ticket);
        $this->sincronizarTicketsConReportesFinalizados((int) $ticket->dependencia_id);
        $ticket->refresh();

        return response()->json($ticket->load(['asignado', 'area', 'equipo', 'historial', 'reportes']));
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'asunto' => 'sometimes|string|max:200',
            'descripcion' => 'sometimes|nullable|string',
            'solicitante' => 'sometimes|nullable|string|max:150',
            'cargo' => 'sometimes|nullable|string|max:150',
            'prioridad' => 'sometimes|in:alta,media,baja',
            'estatus' => 'sometimes|in:abierto,en_proceso,resuelto,cerrado',
            'fecha_limite' => 'sometimes|nullable|date',
            'sla_horas' => 'sometimes|nullable|integer|min:1',
            'usuario_asignado_id' => 'sometimes|nullable|integer',
            'area_id' => 'sometimes|nullable|integer',
            'equipo_id' => 'sometimes|nullable|integer',
        ]);

        if (array_key_exists('usuario_asignado_id', $data)) {
            $nuevoAsignado = $this->resolveFk($request, 'usuario_asignado_id', Usuario::class);
            if ($nuevoAsignado !== null && (int) $ticket->usuario_asignado_id !== $nuevoAsignado) {
                $this->authorize('assign', $ticket);
            }
            $data['usuario_asignado_id'] = $nuevoAsignado;
        }
        if (array_key_exists('area_id', $data)) {
            $data['area_id'] = $this->resolveFk($request, 'area_id', Area::class);
        }
        if (array_key_exists('equipo_id', $data)) {
            $data['equipo_id'] = $this->resolveFk($request, 'equipo_id', Equipo::class);
        }

        $ticket->forceFill($data)->save();

        return response()->json($ticket->load(['asignado', 'area', 'equipo']));
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();

        return response()->json(['message' => 'deleted']);
    }

    public function asignar(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', $ticket);

        $request->validate([
            'usuario_asignado_id' => 'required|integer',
        ]);

        $usuarioId = $this->resolveFk($request, 'usuario_asignado_id', Usuario::class);
        if ($usuarioId === null) {
            return response()->json(['message' => 'El usuario a asignar no existe en tu dependencia.'], 422);
        }

        $ticket = DB::transaction(function () use ($ticket, $usuarioId) {
            $ticket->update([
                'usuario_asignado_id' => $usuarioId,
                'estatus' => $ticket->estatus === 'abierto' ? 'en_proceso' : $ticket->estatus,
            ]);

            try {
                \App\Models\Notificacion::create([
                    'dependencia_id' => $ticket->dependencia_id,
                    'tipo' => 'ticket_asignado',
                    'referencia_tipo' => 'ticket',
                    'referencia_id' => $ticket->id,
                    'destinatario' => $ticket->asignado->email ?? '',
                    'usuario_id' => $usuarioId,
                    'asunto' => "Ticket {$ticket->folio} asignado",
                    'cuerpo' => "Se te asignó el ticket {$ticket->folio}: {$ticket->asunto}",
                    'estatus' => 'pendiente',
                ]);
            } catch (\Throwable $e) {
                report($e);
            }

            return $ticket;
        });

        return response()->json($ticket->load('asignado'));
    }

    public function tecnicos(Request $request): JsonResponse
    {
        try {
            $user = $this->authUser($request);
            $idEmpresa = $user->dependencia_id ?? $user->id_dependencia ?? 1;

            $tecnicos = Usuario::where('dependencia_id', $idEmpresa)
                ->where('estado', true)
                ->orderBy('nombre', 'asc')
                ->get()
                ->map(function ($u) use ($idEmpresa) {
                    $numEmp = $u->numero_empleado ?: ('EMP-'.$idEmpresa.str_pad((string) $u->id, 3, '0', STR_PAD_LEFT));

                    return [
                        'id' => $u->id,
                        'idUsuario' => $u->id,
                        'id_usuario' => $u->id,
                        'idDependencia' => (int) $u->dependencia_id,
                        'dependencia_id' => (int) $u->dependencia_id,
                        'nombre' => $u->nombre,
                        'apellidos' => $u->apellidos ?: '',
                        'correo' => $u->email,
                        'email' => $u->email,
                        'rol' => (int) $u->rol,
                        'estado' => (bool) $u->estado,
                        'numeroEmpleado' => $numEmp,
                        'numero_empleado' => $u->numero_empleado,
                    ];
                });

            return response()->json([
                'ok' => true,
                'success' => true,
                'data' => $tecnicos,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en TicketController::tecnicos: ' . $e->getMessage());

            return response()->json([
                'ok' => false,
                'success' => false,
                'message' => 'Error al obtener técnicos.',
            ], 500);
        }
    }
}
