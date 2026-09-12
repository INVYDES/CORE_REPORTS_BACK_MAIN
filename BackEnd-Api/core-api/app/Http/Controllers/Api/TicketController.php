<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Area;
use App\Models\Equipo;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    use EnsuresDependencia;

    public function index(Request $request): JsonResponse
    {
        $q = Ticket::with(['asignado', 'area', 'equipo']);

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

        return response()->json($q->orderByDesc('fecha_solicitud')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $data = $request->validated();

        // FKs validadas contra la dependencia activa (evita cross-tenant)
        $data['usuario_asignado_id'] = $this->validatedDependenciaId($request, 'usuario_asignado_id', Usuario::class);
        $data['area_id'] = $this->validatedDependenciaId($request, 'area_id', Area::class);
        $data['equipo_id'] = $this->validatedDependenciaId($request, 'equipo_id', Equipo::class);

        if ($this->validatedDependenciaId($request, 'usuario_asignado_id', Usuario::class) !== null) {
            $this->authorize('assign', Ticket::class);
        }

        $data['folio'] = $data['folio'] ?? Ticket::generarFolio($request->user()->dependencia_id);
        $data['estatus'] = $data['estatus'] ?? 'abierto';
        $data['prioridad'] = $data['prioridad'] ?? 'media';
        $data['fecha_solicitud'] = now();

        $ticket = Ticket::create($data);

        return response()->json($ticket->load(['asignado', 'area', 'equipo']), 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->ensureOwnDependencia($ticket);

        return response()->json($ticket->load(['asignado', 'area', 'equipo', 'historial', 'reportes']));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validated();

        if (array_key_exists('usuario_asignado_id', $data)) {
            $nuevoAsignado = $this->validatedDependenciaId($request, 'usuario_asignado_id', Usuario::class);
            if ($nuevoAsignado !== null && (int) $ticket->usuario_asignado_id !== $nuevoAsignado) {
                $this->authorize('assign', $ticket);
            }
            $data['usuario_asignado_id'] = $nuevoAsignado;
        }
        if (array_key_exists('area_id', $data)) {
            $data['area_id'] = $this->validatedDependenciaId($request, 'area_id', Area::class);
        }
        if (array_key_exists('equipo_id', $data)) {
            $data['equipo_id'] = $this->validatedDependenciaId($request, 'equipo_id', Equipo::class);
        }

        $estadoAnterior = $ticket->estatus;
        $ticket->update($data);

        // Historial de cambios de estatus
        if (isset($data['estatus']) && $data['estatus'] !== $estadoAnterior) {
            $ticket->historial()->create([
                'dependencia_id' => $ticket->dependencia_id,
                'estatus_anterior' => $estadoAnterior,
                'estatus_nuevo' => $data['estatus'],
                'cambiado_por' => $request->user()->id,
                'fecha_cambio' => now(),
            ]);
        }

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

        $data = $request->validate([
            'usuario_asignado_id' => 'required|integer',
        ]);

        $usuarioId = $this->validatedDependenciaId($request, 'usuario_asignado_id', Usuario::class);
        if ($usuarioId === null) {
            return response()->json(['message' => 'El usuario a asignar no existe en tu dependencia.'], 422);
        }

        $ticket = DB::transaction(function () use ($request, $ticket, $usuarioId) {
            $estatusAnterior = $ticket->estatus;

            $ticket->update([
                'usuario_asignado_id' => $usuarioId,
                'estatus' => $ticket->estatus === 'abierto' ? 'en_proceso' : $ticket->estatus,
            ]);

            if ($ticket->estatus !== $estatusAnterior) {
                $ticket->historial()->create([
                    'dependencia_id' => $ticket->dependencia_id,
                    'estatus_anterior' => $estatusAnterior,
                    'estatus_nuevo' => $ticket->estatus,
                    'cambiado_por' => $request->user()->id,
                    'fecha_cambio' => now(),
                ]);
            }

            // Notificación (el correo se envía vía cola / comando, no bloquea la petición)
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

            return $ticket;
        });

        return response()->json($ticket->load('asignado'));
    }
}
