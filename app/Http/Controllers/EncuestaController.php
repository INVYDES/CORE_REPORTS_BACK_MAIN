<?php

namespace App\Http\Controllers;

use App\Models\Encuesta;
use App\Models\Reporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EncuestaController extends Controller
{
    /**
     * POST /api/encuestas
     * Registra o actualiza la evaluación de servicio de un cliente para un reporte
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idReporte'    => 'required|integer|exists:reportes,id_reporte',
            'calificacion' => 'required|integer|min:1|max:10',
            'nombreFirma'  => 'required|string|max:150',
            'comentarios'  => 'nullable|string',
        ], [
            'idReporte.required'    => 'El ID del reporte es obligatorio.',
            'calificacion.required' => 'Debes asignar una calificación de 1 a 10.',
            'nombreFirma.required'  => 'El nombre y firma del cliente es obligatorio.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de evaluación inválidos.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $encuesta = Encuesta::updateOrCreate(
            ['id_reporte' => $request->idReporte],
            [
                'calificacion' => $request->calificacion,
                'comentarios'  => $request->comentarios ?: '',
                'nombre_firma' => $request->nombreFirma,
                'fecha'        => now()->toDateTimeString(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Evaluación de servicio guardada con éxito en MySQL.',
            'data'    => [
                'idEncuesta'   => $encuesta->id_encuesta,
                'idReporte'    => $encuesta->id_reporte,
                'calificacion' => (int) $encuesta->calificacion,
                'comentarios'  => $encuesta->comentarios,
                'nombreFirma'  => $encuesta->nombre_firma,
                'fecha'        => $encuesta->fecha,
            ]
        ], 200);
    }

    /**
     * GET /api/encuestas/{idReporte}
     * Obtiene la encuesta de un reporte
     */
    public function show($idReporte)
    {
        $encuesta = Encuesta::where('id_reporte', $idReporte)->first();

        if (!$encuesta) {
            return response()->json([
                'success' => false,
                'message' => 'No hay encuesta registrada para este reporte.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'idEncuesta'   => $encuesta->id_encuesta,
                'idReporte'    => $encuesta->id_reporte,
                'calificacion' => (int) $encuesta->calificacion,
                'comentarios'  => $encuesta->comentarios,
                'nombreFirma'  => $encuesta->nombre_firma,
                'fecha'        => $encuesta->fecha,
            ]
        ], 200);
    }
}
