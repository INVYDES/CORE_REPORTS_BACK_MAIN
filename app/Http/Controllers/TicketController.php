<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    /**
     * GET /api/tickets
     * Lista únicamente los tickets de la empresa a la que pertenece el usuario logueado
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $idEmpresa = $user->id_dependencia;

        $query = Ticket::where('id_dependencia', $idEmpresa);

        // Si el usuario no es Admin Técnico (rol 1) ni Admin Comercial (rol 2), solo ve sus propios tickets asignados
        if (!in_array((int)$user->rol, [1, 2])) {
            $query->where(function ($q) use ($user) {
                $nombreCompleto = trim($user->nombre . ' ' . $user->apellidos);
                $q->where('id_tecnico_atendio', $user->id_usuario)
                  ->orWhere('atendio', $nombreCompleto)
                  ->orWhere('atendio', $user->nombre);
            });
        }

        // Traer los tickets ordenados del más reciente al más antiguo
        $tickets = $query
            ->with(['tecnico', 'dependencia'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($t) {
                $data = $t->toArray();
                $data['fechaSolicitud'] = $t->fecha_solicitud;
                $data['fechaAtencion'] = $t->fecha_atencion;
                $data['idDependencia'] = $t->id_dependencia;
                $data['idSubdependencia'] = $t->id_subdependencia;
                return $data;
            });

        return response()->json([
            'success' => true,
            'message' => 'Tickets obtenidos correctamente.',
            'data'    => $tickets
        ], 200);
    }

    /**
     * POST /api/tickets
     * Valida y guarda un nuevo ticket en MySQL
     */
    public function store(Request $request)
    {
        // 1. Validar que no vengan campos vacíos o con formato incorrecto
        $validator = Validator::make($request->all(), [
            'asunto'      => 'required|string|max:255',
            'descripcion' => 'required|string',
            'prioridad'   => 'required|in:Alta,Media,Baja',
        ], [
            'asunto.required'      => 'El asunto del ticket es obligatorio.',
            'descripcion.required' => 'La descripción del problema es obligatoria.',
            'prioridad.required'   => 'Debes seleccionar una prioridad.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de formulario inválidos.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $usuario = $request->user();

        // 2. Generar un folio único para el ticket (Ejemplo: TCK-2026-A1B2)
        $folio = 'TCK-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4));

        // 3. Crear el registro en MySQL mediante el Modelo
        $ticket = Ticket::create([
            'folio'             => $folio,
            'id_dependencia'    => $usuario->id_dependencia,
            'id_subdependencia' => $request->id_subdependencia ?? null,
            'asunto'            => $request->asunto,
            'descripcion'       => $request->descripcion,
            'solicitante'       => $request->solicitante ?: ($usuario->nombre . ' ' . $usuario->apellidos),
            'cargo'             => $request->cargo ?: 'Usuario de Plataforma',
            'prioridad'         => $request->prioridad,
            'estatus'           => 'Abierto',
            'fecha_solicitud'   => now()->toDateString(),
        ]);

        $responseData = $ticket->toArray();
        $responseData['fechaSolicitud'] = $ticket->fecha_solicitud;
        $responseData['idDependencia'] = $ticket->id_dependencia;

        return response()->json([
            'success' => true,
            'message' => 'Ticket creado con éxito.',
            'data'    => $responseData
        ], 201);
    }

    /**
     * GET /api/tickets/{id}
     * Obtiene el detalle de un ticket específico si pertenece a la empresa del usuario
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $idEmpresa = $user->id_dependencia;

        $query = Ticket::where('id_dependencia', $idEmpresa);

        // Si el usuario no es Admin Técnico (rol 1) ni Admin Comercial (rol 2), solo puede ver si le pertenece
        if (!in_array((int)$user->rol, [1, 2])) {
            $query->where(function ($q) use ($user) {
                $nombreCompleto = trim($user->nombre . ' ' . $user->apellidos);
                $q->where('id_tecnico_atendio', $user->id_usuario)
                  ->orWhere('atendio', $nombreCompleto)
                  ->orWhere('atendio', $user->nombre);
            });
        }

        $ticket = $query
            ->with(['tecnico', 'dependencia'])
            ->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket no encontrado o no tienes permiso para verlo.'
            ], 404);
        }

        $data = $ticket->toArray();
        $data['fechaSolicitud'] = $ticket->fecha_solicitud;
        $data['fechaAtencion'] = $ticket->fecha_atencion;
        $data['idDependencia'] = $ticket->id_dependencia;
        $data['idSubdependencia'] = $ticket->id_subdependencia;

        return response()->json([
            'success' => true,
            'message' => 'Ticket obtenido correctamente.',
            'data'    => $data
        ], 200);
    }

    /**
     * PUT /api/tickets/{id}
     * Actualiza el estatus o asignación de un ticket existente
     */
    public function update(Request $request, $id)
    {
        $idEmpresa = $request->user()->id_dependencia;
        $ticket = Ticket::where('id_dependencia', $idEmpresa)->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket no encontrado o no tienes permiso para modificarlo.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'estatus'            => 'nullable|in:Abierto,En Proceso,Resuelto,Cerrado',
            'id_tecnico_atendio' => 'nullable|integer',
            'atendio'            => 'nullable|string|max:255',
            'fecha_atencion'     => 'nullable|date',
            'prioridad'          => 'nullable|in:Alta,Media,Baja',
            'asunto'             => 'nullable|string|max:255',
            'descripcion'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de actualización inválidos.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'estatus',
            'id_tecnico_atendio',
            'atendio',
            'fecha_atencion',
            'prioridad',
            'asunto',
            'descripcion',
        ]);

        // Si se asigna un técnico y no se definió fecha de atención, asignar hoy
        if (!empty($updateData['id_tecnico_atendio']) && empty($updateData['fecha_atencion'])) {
            $updateData['fecha_atencion'] = now()->toDateString();
        }

        // Si el ticket estaba Abierto y ahora se asigna o pone en proceso
        if (!empty($updateData['id_tecnico_atendio']) && empty($updateData['estatus'])) {
            $updateData['estatus'] = 'En Proceso';
        }

        $ticket->update($updateData);
        $ticket->load(['tecnico', 'dependencia']);

        $data = $ticket->toArray();
        $data['fechaSolicitud'] = $ticket->fecha_solicitud;
        $data['fechaAtencion'] = $ticket->fecha_atencion;
        $data['idDependencia'] = $ticket->id_dependencia;
        $data['idSubdependencia'] = $ticket->id_subdependencia;

        return response()->json([
            'success' => true,
            'message' => 'Ticket actualizado con éxito.',
            'data'    => $data
        ], 200);
    }

    /**
     * GET /api/tecnicos
     * Obtiene los usuarios de la empresa para asignación de tickets
     */
    public function tecnicos(Request $request)
    {
        $idEmpresa = $request->user()->id_dependencia;

        $tecnicos = \App\Models\User::where('id_dependencia', $idEmpresa)
            ->where('estado', 1)
            ->orderBy('nombre', 'asc')
            ->get()
            ->map(function ($u) {
                return [
                    'id'             => $u->id_usuario,
                    'idUsuario'      => $u->id_usuario,
                    'id_usuario'     => $u->id_usuario,
                    'nombre'         => $u->nombre,
                    'apellidos'      => $u->apellidos ?: '',
                    'correo'         => $u->correo,
                    'rol'            => (int) $u->rol,
                    'numeroEmpleado' => $u->numero_empleado ?: ('EMP-' . str_pad($u->id_usuario, 3, '0', STR_PAD_LEFT)),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $tecnicos
        ], 200);
    }
}