<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventarioService;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    use EnsuresDependencia;

    public function index(Request $request): JsonResponse
    {
        $q = \App\Models\MaterialCatalogo::query();

        if ($request->filled('activo')) {
            $q->where('activo', $request->boolean('activo'));
        }
        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(fn ($qq) => $qq
                ->where('nombre', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%"));
        }
        if ($request->boolean('stock_bajo')) {
            $q->whereColumn('stock_actual', '<=', 'stock_minimo');
        }

        return response()->json($q->orderBy('nombre')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', \App\Models\MaterialCatalogo::class);

        $data = $request->validate([
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'unidad_base' => 'required|string|max:20',
            'costo_unitario' => 'sometimes|numeric|min:0',
            'stock_actual' => 'sometimes|numeric|min:0',
            'stock_minimo' => 'sometimes|numeric|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        $material = \App\Models\MaterialCatalogo::create($data);

        return response()->json($material, 201);
    }

    public function show(\App\Models\MaterialCatalogo $material): JsonResponse
    {
        $this->ensureOwnDependencia($material);

        return response()->json($material);
    }

    public function update(Request $request, \App\Models\MaterialCatalogo $material): JsonResponse
    {
        $this->authorize('update', $material);

        $data = $request->validate([
            'nombre' => 'sometimes|string|max:150',
            'descripcion' => 'sometimes|nullable|string',
            'unidad_base' => 'sometimes|string|max:20',
            'costo_unitario' => 'sometimes|numeric|min:0',
            'stock_minimo' => 'sometimes|numeric|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        // El stock nunca se edita directamente: solo vía entradas/salidas
        unset($data['stock_actual']);

        $material->update($data);

        return response()->json($material);
    }

    public function destroy(\App\Models\MaterialCatalogo $material): JsonResponse
    {
        $this->authorize('delete', $material);
        $material->delete();

        return response()->json(['message' => 'deleted']);
    }

    public function entrada(Request $request, \App\Models\MaterialCatalogo $material, InventarioService $svc): JsonResponse
    {
        $this->authorize('registrarEntrada', $material);

        $data = $request->validate([
            'cantidad' => 'required|numeric|min:0.01',
            'notas' => 'nullable|string|max:500',
        ]);

        $mov = $svc->registrarEntrada(
            $material->dependencia_id,
            $material->id,
            (float) $data['cantidad'],
            $request->user()->id,
            $data['notas'] ?? null
        );

        return response()->json($mov, 201);
    }

    public function movimientos(Request $request, \App\Models\MaterialCatalogo $material): JsonResponse
    {
        $this->authorize('viewMovimientos', $material);

        $q = \App\Models\MovimientoInventario::where('material_id', $material->id);

        if ($request->filled('tipo')) {
            $q->where('tipo', $request->string('tipo'));
        }

        return response()->json($q->orderByDesc('created_at')->paginate(min((int) $request->get('per_page', 20), 100)));
    }
}
