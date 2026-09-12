<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipoController extends Controller
{
    use EnsuresDependencia;

    public function index(Request $request): JsonResponse
    {
        $q = \App\Models\Equipo::with('area');

        if ($request->filled('area_id')) {
            $q->where('area_id', (int) $request->area_id);
        }
        if ($request->filled('activo')) {
            $q->where('activo', $request->boolean('activo'));
        }
        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(fn ($qq) => $qq
                ->where('codigo', 'like', "%{$s}%")
                ->orWhere('nombre', 'like', "%{$s}%")
                ->orWhere('numero_serie', 'like', "%{$s}%"));
        }

        return response()->json($q->orderBy('nombre')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', \App\Models\Equipo::class);

        $data = $request->validate([
            'codigo' => 'required|string|max:50',
            'nombre' => 'required|string|max:150',
            'area_id' => 'nullable|integer',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'numero_serie' => 'nullable|string|max:100',
            'fecha_instalacion' => 'nullable|date',
            'activo' => 'sometimes|boolean',
        ]);

        $data['area_id'] = $this->validatedDependenciaId($request, 'area_id', \App\Models\Area::class);
        $data['activo'] = $data['activo'] ?? true;

        $equipo = \App\Models\Equipo::create($data);

        return response()->json($equipo->load('area'), 201);
    }

    public function show(\App\Models\Equipo $equipo): JsonResponse
    {
        $this->ensureOwnDependencia($equipo);

        return response()->json($equipo->load('area'));
    }

    public function update(Request $request, \App\Models\Equipo $equipo): JsonResponse
    {
        $this->authorize('update', $equipo);

        $data = $request->validate([
            'codigo' => 'sometimes|string|max:50',
            'nombre' => 'sometimes|string|max:150',
            'area_id' => 'sometimes|nullable|integer',
            'marca' => 'sometimes|nullable|string|max:100',
            'modelo' => 'sometimes|nullable|string|max:100',
            'numero_serie' => 'sometimes|nullable|string|max:100',
            'fecha_instalacion' => 'sometimes|nullable|date',
            'activo' => 'sometimes|boolean',
        ]);

        if (array_key_exists('area_id', $data)) {
            $data['area_id'] = $this->validatedDependenciaId($request, 'area_id', \App\Models\Area::class);
        }

        $equipo->update($data);

        return response()->json($equipo->load('area'));
    }

    public function destroy(\App\Models\Equipo $equipo): JsonResponse
    {
        $this->authorize('delete', $equipo);
        $equipo->delete();

        return response()->json(['message' => 'deleted']);
    }
}
