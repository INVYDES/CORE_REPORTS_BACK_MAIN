<?php

namespace App\Http\Controllers;

use App\Models\ServicioProgramado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServicioController extends Controller
{
    /**
     * GET /api/servicios
     * Lista todos los servicios programados de la empresa
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $idEmpresa = $user->id_dependencia;

        $query = ServicioProgramado::where('id_dependencia', $idEmpresa);

        // Si el usuario no es Admin Técnico (rol 1) ni Admin Comercial (rol 2), solo ve sus propios servicios asignados
        if (!in_array((int)$user->rol, [1, 2])) {
            $query->where(function ($q) use ($user) {
                $nombreCompleto = trim($user->nombre . ' ' . $user->apellidos);
                $q->where('id_tecnico_asignado', $user->id_usuario)
                  ->orWhere('asignado_a', $nombreCompleto)
                  ->orWhere('asignado_a', $user->nombre);
            });
        }

        $servicios = $query
            ->with(['subdependencia', 'tecnico'])
            ->orderBy('fecha_vencimiento', 'asc')
            ->get()
            ->map(function ($s) {
                return [
                    'id'                 => $s->id,
                    'folio'              => $s->folio,
                    'asunto'             => $s->asunto,
                    'descripcion'        => $s->descripcion,
                    'solicitante'        => $s->solicitante,
                    'cargo'              => $s->cargo,
                    'idDependencia'      => $s->id_dependencia,
                    'idSubdependencia'   => $s->id_subdependencia,
                    'idTecnicoAsignado'  => $s->id_tecnico_asignado,
                    'asignadoA'          => $s->asignado_a,
                    'fechaAsignacion'    => $s->fecha_asignacion,
                    'fechaVencimiento'   => $s->fecha_vencimiento,
                    'estatus'            => $s->estatus,
                    'prioridad'          => $s->prioridad,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Servicios obtenidos correctamente.',
            'data'    => $servicios
        ], 200);
    }

    /**
     * POST /api/servicios
     * Registra un nuevo servicio programado en MySQL
     */
    public function store(Request $request)
    {
        $idEmpresa = $request->user()->id_dependencia;
        $usuario = $request->user();

        $validator = Validator::make($request->all(), [
            'asunto'            => 'required|string|max:255',
            'descripcion'       => 'required|string',
            'fecha_vencimiento' => 'required|date',
            'prioridad'         => 'required|string',
        ], [
            'asunto.required'            => 'El asunto del servicio es obligatorio.',
            'descripcion.required'       => 'La descripción técnica es obligatoria.',
            'fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de formulario inválidos.',
                'errors'  => $validator->errors()
            ], 422);
        }

        // Generar folio único (ejemplo: SVC-2026-F1E2)
        $folio = 'SVC-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4));

        // Si se asignó un técnico por ID, obtener su nombre
        $asignadoA = $request->asignado_a ?: null;
        if ($request->id_tecnico_asignado) {
            $tech = User::where('id_dependencia', $idEmpresa)->find($request->id_tecnico_asignado);
            if ($tech) {
                $asignadoA = $tech->nombre . ' ' . $tech->apellidos;
            }
        }

        $servicio = ServicioProgramado::create([
            'folio'               => $folio,
            'id_dependencia'      => $idEmpresa,
            'id_subdependencia'   => $request->id_subdependencia ?: null,
            'asunto'              => $request->asunto,
            'descripcion'         => $request->descripcion,
            'solicitante'         => $request->solicitante ?: ($usuario->nombre . ' ' . $usuario->apellidos),
            'cargo'               => $request->cargo ?: 'Administrador de Operaciones',
            'id_tecnico_asignado' => $request->id_tecnico_asignado ?: null,
            'asignado_a'          => $asignadoA,
            'fecha_asignacion'    => $request->fecha_asignacion ?: now()->toDateString(),
            'fecha_vencimiento'   => $request->fecha_vencimiento,
            'estatus'             => $request->estatus ?: 'Programado',
            'prioridad'           => $request->prioridad ?: 'Media',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Servicio programado creado con éxito.',
            'data'    => [
                'id'                 => $servicio->id,
                'folio'              => $servicio->folio,
                'asunto'             => $servicio->asunto,
                'descripcion'        => $servicio->descripcion,
                'solicitante'        => $servicio->solicitante,
                'cargo'              => $servicio->cargo,
                'idDependencia'      => $servicio->id_dependencia,
                'idSubdependencia'   => $servicio->id_subdependencia,
                'idTecnicoAsignado'  => $servicio->id_tecnico_asignado,
                'asignadoA'          => $servicio->asignado_a,
                'fechaAsignacion'    => $servicio->fecha_asignacion,
                'fechaVencimiento'   => $servicio->fecha_vencimiento,
                'estatus'            => $servicio->estatus,
                'prioridad'          => $servicio->prioridad,
            ]
        ], 201);
    }

    /**
     * GET /api/servicios/{id}
     * Detalle de un servicio específico
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $idEmpresa = $user->id_dependencia;

        $query = ServicioProgramado::where('id_dependencia', $idEmpresa);

        // Si el usuario no es Admin Técnico (rol 1) ni Admin Comercial (rol 2), solo puede ver si le fue asignado
        if (!in_array((int)$user->rol, [1, 2])) {
            $query->where(function ($q) use ($user) {
                $nombreCompleto = trim($user->nombre . ' ' . $user->apellidos);
                $q->where('id_tecnico_asignado', $user->id_usuario)
                  ->orWhere('asignado_a', $nombreCompleto)
                  ->orWhere('asignado_a', $user->nombre);
            });
        }

        $s = $query
            ->with(['subdependencia', 'tecnico'])
            ->find($id);

        if (!$s) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado o no autorizado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                 => $s->id,
                'folio'              => $s->folio,
                'asunto'             => $s->asunto,
                'descripcion'        => $s->descripcion,
                'solicitante'        => $s->solicitante,
                'cargo'              => $s->cargo,
                'idDependencia'      => $s->id_dependencia,
                'idSubdependencia'   => $s->id_subdependencia,
                'idTecnicoAsignado'  => $s->id_tecnico_asignado,
                'asignadoA'          => $s->asignado_a,
                'fechaAsignacion'    => $s->fecha_asignacion,
                'fechaVencimiento'   => $s->fecha_vencimiento,
                'estatus'            => $s->estatus,
                'prioridad'          => $s->prioridad,
            ]
        ], 200);
    }

    /**
     * PUT /api/servicios/{id}
     * Actualiza estatus o asignación de un servicio
     */
    public function update(Request $request, $id)
    {
        $idEmpresa = $request->user()->id_dependencia;
        $servicio = ServicioProgramado::where('id_dependencia', $idEmpresa)->find($id);

        if (!$servicio) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'estatus'             => 'nullable|in:Programado,En Proceso,Realizado,Vencido',
            'id_tecnico_asignado' => 'nullable|integer',
            'asignado_a'          => 'nullable|string|max:150',
            'fecha_vencimiento'   => 'nullable|date',
            'prioridad'           => 'nullable|string',
            'asunto'              => 'nullable|string|max:255',
            'descripcion'         => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'estatus',
            'id_tecnico_asignado',
            'asignado_a',
            'fecha_vencimiento',
            'prioridad',
            'asunto',
            'descripcion',
        ]);

        if (!empty($updateData['id_tecnico_asignado']) && empty($updateData['asignado_a'])) {
            $tech = User::where('id_dependencia', $idEmpresa)->find($updateData['id_tecnico_asignado']);
            if ($tech) {
                $updateData['asignado_a'] = $tech->nombre . ' ' . $tech->apellidos;
            }
        }

        $servicio->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Servicio actualizado correctamente.',
            'data'    => [
                'id'                 => $servicio->id,
                'folio'              => $servicio->folio,
                'asunto'             => $servicio->asunto,
                'descripcion'        => $servicio->descripcion,
                'solicitante'        => $servicio->solicitante,
                'cargo'              => $servicio->cargo,
                'idDependencia'      => $servicio->id_dependencia,
                'idSubdependencia'   => $servicio->id_subdependencia,
                'idTecnicoAsignado'  => $servicio->id_tecnico_asignado,
                'asignadoA'          => $servicio->asignado_a,
                'fechaAsignacion'    => $servicio->fecha_asignacion,
                'fechaVencimiento'   => $servicio->fecha_vencimiento,
                'estatus'            => $servicio->estatus,
                'prioridad'          => $servicio->prioridad,
            ]
        ], 200);
    }
}
