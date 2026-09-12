<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DependenciaController extends Controller
{
    /**
     * La dependencia visible siempre es la del usuario autenticado
     * (el sistema es multi-tenant con una sola dependencia por sesión).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Dependencia::where('id', $request->user()->dependencia_id)->paginate(min((int) $request->get('per_page', 15), 100))
        );
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->dependencia);
    }

    public function update(Request $request): JsonResponse
    {
        $dependencia = $request->user()->dependencia;

        if (! $dependencia) {
            return response()->json(['message' => 'Dependencia no encontrada'], 404);
        }

        $data = $request->validate([
            'nombre' => 'sometimes|string|max:150',
            'rfc' => 'sometimes|nullable|string|max:20',
            'telefono' => 'sometimes|nullable|string|max:25',
            'correo_contacto' => 'sometimes|nullable|email',
            'correo_reportes' => 'sometimes|nullable|email',
            'datos_facturacion' => 'sometimes|nullable|string|max:500',
        ]);

        // Límites y licencia solo cambian vía compra/renovación, nunca por este endpoint
        $dependencia->update($data);

        return response()->json($dependencia);
    }
}
