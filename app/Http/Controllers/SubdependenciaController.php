<?php

namespace App\Http\Controllers;

use App\Models\Subdependencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubdependenciaController extends Controller
{
    /**
     * GET /api/subdependencias
     * Lista las subáreas/plantas activas de la empresa del usuario en sesión
     */
    public function index(Request $request)
    {
        $idEmpresa = $request->user()->id_dependencia;

        $subareas = Subdependencia::where('id_dependencia', $idEmpresa)
            ->where('activo', 1)
            ->orderBy('nombre', 'asc')
            ->get()
            ->map(function ($s) {
                return [
                    'idSubdependencia' => $s->id_subdependencia,
                    'idDependencia'    => $s->id_dependencia,
                    'nombre'           => $s->nombre,
                    'activo'           => (bool) $s->activo,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Subáreas obtenidas correctamente.',
            'data'    => $subareas
        ], 200);
    }

    /**
     * POST /api/subdependencias
     * Crea una nueva subárea para la empresa
     */
    public function store(Request $request)
    {
        $idEmpresa = $request->user()->id_dependencia;

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:150',
        ], [
            'nombre.required' => 'El nombre del área o planta es obligatorio.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $subarea = Subdependencia::create([
            'id_dependencia' => $idEmpresa,
            'nombre'         => $request->nombre,
            'activo'         => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subárea creada exitosamente.',
            'data'    => [
                'idSubdependencia' => $subarea->id_subdependencia,
                'idDependencia'    => $subarea->id_dependencia,
                'nombre'           => $subarea->nombre,
                'activo'           => (bool) $subarea->activo,
            ]
        ], 201);
    }

    /**
     * PUT /api/subdependencias/{id}
     * Modifica el nombre o estatus de una subárea
     */
    public function update(Request $request, $id)
    {
        $idEmpresa = $request->user()->id_dependencia;
        $subarea = Subdependencia::where('id_dependencia', $idEmpresa)->find($id);

        if (!$subarea) {
            return response()->json([
                'success' => false,
                'message' => 'Subárea no encontrada o no pertenece a tu empresa.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:150',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $subarea->update($request->only(['nombre', 'activo']));

        return response()->json([
            'success' => true,
            'message' => 'Subárea actualizada con éxito.',
            'data'    => [
                'idSubdependencia' => $subarea->id_subdependencia,
                'idDependencia'    => $subarea->id_dependencia,
                'nombre'           => $subarea->nombre,
                'activo'           => (bool) $subarea->activo,
            ]
        ], 200);
    }

    /**
     * DELETE /api/subdependencias/{id}
     * Desactiva / Elimina una subárea
     */
    public function destroy(Request $request, $id)
    {
        $idEmpresa = $request->user()->id_dependencia;
        $subarea = Subdependencia::where('id_dependencia', $idEmpresa)->find($id);

        if (!$subarea) {
            return response()->json([
                'success' => false,
                'message' => 'Subárea no encontrada.'
            ], 404);
        }

        $subarea->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subárea eliminada exitosamente.'
        ], 200);
    }
}
