<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * GET /api/usuarios
     * Lista todos los miembros del equipo de la empresa
     */
    public function index(Request $request)
    {
        $idEmpresa = $request->user()->id_dependencia;

        $usuarios = User::where('id_dependencia', $idEmpresa)
            ->orderBy('id_usuario', 'asc')
            ->get()
            ->map(function ($u) {
                return [
                    'idUsuario'      => $u->id_usuario,
                    'idDependencia'  => $u->id_dependencia,
                    'numeroEmpleado' => $u->numero_empleado ?: ('EMP-' . str_pad($u->id_usuario, 3, '0', STR_PAD_LEFT)),
                    'rol'            => (int) $u->rol,
                    'nombre'         => $u->nombre,
                    'apellidos'      => $u->apellidos ?: '',
                    'correo'         => $u->correo,
                    'estado'         => (int) $u->estado,
                    'fechaCreacion'  => $u->created_at ? $u->created_at->toDateString() : date('Y-m-d'),
                    'ultimoAcceso'   => $u->ultimo_acceso ? $u->ultimo_acceso->toDateTimeString() : null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Usuarios obtenidos correctamente.',
            'data'    => $usuarios
        ], 200);
    }

    /**
     * POST /api/usuarios
     * Registra un nuevo usuario (Técnico, Administrador, etc.) en MySQL
     */
    public function store(Request $request)
    {
        $idEmpresa = $request->user()->id_dependencia;

        $validator = Validator::make($request->all(), [
            'nombre'         => 'required|string|max:100',
            'apellidos'      => 'required|string|max:100',
            'correo'         => 'required|email|max:150|unique:usuarios,correo',
            'password'       => 'required|string|min:6',
            'rol'            => 'required|in:0,1,2,3',
            'numeroEmpleado' => 'nullable|string|max:50',
        ], [
            'nombre.required'    => 'El nombre es obligatorio.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'correo.required'    => 'El correo electrónico es obligatorio.',
            'correo.email'       => 'El formato de correo no es válido.',
            'correo.unique'      => 'Ya existe un usuario registrado con este correo.',
            'password.required'  => 'La contraseña es obligatoria.',
            'password.min'       => 'La contraseña debe tener al menos 6 caracteres.',
            'rol.required'       => 'Debes asignar un rol.',
            'rol.in'             => 'El rol seleccionado no es válido.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos al crear usuario.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $nuevoUsuario = User::create([
            'id_dependencia'  => $idEmpresa,
            'numero_empleado' => $request->numeroEmpleado ?: ('EMP-' . rand(100, 999)),
            'rol'             => (int) $request->rol,
            'nombre'          => $request->nombre,
            'apellidos'       => $request->apellidos,
            'correo'          => $request->correo,
            'password'        => Hash::make($request->password),
            'estado'          => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente en MySQL.',
            'data'    => [
                'idUsuario'      => $nuevoUsuario->id_usuario,
                'idDependencia'  => $nuevoUsuario->id_dependencia,
                'numeroEmpleado' => $nuevoUsuario->numero_empleado,
                'rol'            => (int) $nuevoUsuario->rol,
                'nombre'         => $nuevoUsuario->nombre,
                'apellidos'      => $nuevoUsuario->apellidos,
                'correo'         => $nuevoUsuario->correo,
                'estado'         => (int) $nuevoUsuario->estado,
                'fechaCreacion'  => now()->toDateString(),
                'ultimoAcceso'   => null,
            ]
        ], 201);
    }

    /**
     * PUT /api/usuarios/{id}
     * Actualiza la información de un usuario
     */
    public function update(Request $request, $id)
    {
        $idEmpresa = $request->user()->id_dependencia;
        $usuario = User::where('id_dependencia', $idEmpresa)->find($id);

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado o no pertenece a tu empresa.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'         => 'sometimes|required|string|max:100',
            'apellidos'      => 'sometimes|required|string|max:100',
            'correo'         => 'sometimes|required|email|max:150|unique:usuarios,correo,' . $id . ',id_usuario',
            'password'       => 'nullable|string|min:6',
            'rol'            => 'sometimes|required|in:0,1,2,3',
            'numeroEmpleado' => 'nullable|string|max:50',
            'estado'         => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos al actualizar usuario.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $updateData = [];
        if ($request->has('nombre')) $updateData['nombre'] = $request->nombre;
        if ($request->has('apellidos')) $updateData['apellidos'] = $request->apellidos;
        if ($request->has('correo')) $updateData['correo'] = $request->correo;
        if ($request->has('rol')) $updateData['rol'] = (int) $request->rol;
        if ($request->has('estado')) $updateData['estado'] = (int) $request->estado;
        if ($request->has('numeroEmpleado')) $updateData['numero_empleado'] = $request->numeroEmpleado;
        if (!empty($request->password)) {
            $updateData['password'] = Hash::make($request->password);
        }

        $usuario->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente en MySQL.',
            'data'    => [
                'idUsuario'      => $usuario->id_usuario,
                'idDependencia'  => $usuario->id_dependencia,
                'numeroEmpleado' => $usuario->numero_empleado,
                'rol'            => (int) $usuario->rol,
                'nombre'         => $usuario->nombre,
                'apellidos'      => $usuario->apellidos,
                'correo'         => $usuario->correo,
                'estado'         => (int) $usuario->estado,
                'fechaCreacion'  => $usuario->created_at ? $usuario->created_at->toDateString() : date('Y-m-d'),
                'ultimoAcceso'   => $usuario->ultimo_acceso ? $usuario->ultimo_acceso->toDateTimeString() : null,
            ]
        ], 200);
    }
}
