<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    use EnsuresDependencia;

    public function index(Request $request): JsonResponse
    {
        $q = \App\Models\Area::withCount(['tickets', 'servicios']);

        if ($request->filled('activa')) {
            $q->where('activa', $request->boolean('activa'));
        }
        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(fn ($qq) => $qq
                ->where('codigo', 'like', "%{$s}%")
                ->orWhere('nombre', 'like', "%{$s}%"));
        }

        return response()->json($q->orderBy('nombre')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', \App\Models\Area::class);

        $data = $request->validate([
            'codigo' => 'required|string|max:20',
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'activa' => 'sometimes|boolean',
        ]);

        $area = \App\Models\Area::create($data + ['activa' => $data['activa'] ?? true]);

        return response()->json($area, 201);
    }

    public function show(\App\Models\Area $area): JsonResponse
    {
        $this->ensureOwnDependencia($area);

        return response()->json($area);
    }

    public function update(Request $request, \App\Models\Area $area): JsonResponse
    {
        $this->authorize('update', $area);

        $data = $request->validate([
            'codigo' => 'sometimes|string|max:20',
            'nombre' => 'sometimes|string|max:150',
            'descripcion' => 'sometimes|nullable|string',
            'activa' => 'sometimes|boolean',
        ]);

        $area->update($data);

        return response()->json($area);
    }

    public function destroy(\App\Models\Area $area): JsonResponse
    {
        $this->authorize('delete', $area);
        $area->delete();

        return response()->json(['message' => 'deleted']);
    }
}
