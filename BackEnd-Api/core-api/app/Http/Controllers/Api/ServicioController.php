<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    use EnsuresDependencia;

    public function index(Request $request): JsonResponse
    {
        $q = \App\Models\Servicio::with(['asignado', 'area', 'equipo']);

        if ($request->filled('estatus')) {
            $q->where('estatus', $request->string('estatus'));
        }
        if ($request->filled('categoria')) {
            $q->where('categoria', $request->string('categoria'));
        }
        if ($request->filled('prioridad')) {
            $q->where('prioridad', $request->string('prioridad'));
        }
        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(fn ($qq) => $qq
                ->where('folio', 'like', "%{$s}%")
                ->orWhere('asunto', 'like', "%{$s}%"));
        }

        return response()->json($q->orderBy('fecha_vencimiento')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', \App\Models\Servicio::class);

        $data = $request->validate([
            'folio' => 'sometimes|string|max:50',
            'asunto' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'categoria' => 'nullable|in:preventivo,correctivo,instalacion,mejora,diagnostico',
            'fecha_asignacion' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date',
            'prioridad' => 'nullable|in:alta,media,baja',
            'solicitante' => 'nullable|string|max:150',
            'cargo' => 'nullable|string|max:150',
            'usuario_asignado_id' => 'nullable|integer',
            'area_id' => 'nullable|integer',
            'equipo_id' => 'nullable|integer',
        ]);

        $data['usuario_asignado_id'] = $this->validatedDependenciaId($request, 'usuario_asignado_id', Usuario::class);
        $data['area_id'] = $this->validatedDependenciaId($request, 'area_id', \App\Models\Area::class);
        $data['equipo_id'] = $this->validatedDependenciaId($request, 'equipo_id', \App\Models\Equipo::class);

        $data['folio'] = $data['folio'] ?? \App\Models\Servicio::generarFolio($request->user()->dependencia_id);
        $data['estatus'] = $data['estatus'] ?? 'programado';

        $servicio = \App\Models\Servicio::create($data);

        return response()->json($servicio->load(['asignado', 'area', 'equipo']), 201);
    }

    public function show(\App\Models\Servicio $servicio): JsonResponse
    {
        $this->ensureOwnDependencia($servicio);

        return response()->json($servicio->load(['asignado', 'area', 'equipo', 'historial', 'reportes']));
    }

    public function update(Request $request, \App\Models\Servicio $servicio): JsonResponse
    {
        $this->authorize('update', $servicio);

        $data = $request->validate([
            'asunto' => 'sometimes|string|max:200',
            'descripcion' => 'sometimes|nullable|string',
            'categoria' => 'sometimes|in:preventivo,correctivo,instalacion,mejora,diagnostico',
            'fecha_asignacion' => 'sometimes|nullable|date',
            'fecha_vencimiento' => 'sometimes|nullable|date',
            'estatus' => 'sometimes|in:programado,en_proceso,realizado,vencido',
            'prioridad' => 'sometimes|in:alta,media,baja',
            'usuario_asignado_id' => 'sometimes|nullable|integer',
            'area_id' => 'sometimes|nullable|integer',
            'equipo_id' => 'sometimes|nullable|integer',
        ]);

        if (array_key_exists('usuario_asignado_id', $data)) {
            $data['usuario_asignado_id'] = $this->validatedDependenciaId($request, 'usuario_asignado_id', Usuario::class);
        }
        if (array_key_exists('area_id', $data)) {
            $data['area_id'] = $this->validatedDependenciaId($request, 'area_id', \App\Models\Area::class);
        }
        if (array_key_exists('equipo_id', $data)) {
            $data['equipo_id'] = $this->validatedDependenciaId($request, 'equipo_id', \App\Models\Equipo::class);
        }

        $servicio->update($data);

        return response()->json($servicio->load(['asignado', 'area', 'equipo']));
    }

    public function destroy(\App\Models\Servicio $servicio): JsonResponse
    {
        $this->authorize('delete', $servicio);
        $servicio->delete();

        return response()->json(['message' => 'deleted']);
    }
}
