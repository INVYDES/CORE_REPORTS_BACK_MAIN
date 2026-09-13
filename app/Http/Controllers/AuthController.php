<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Iniciar sesión y emitir Bearer Token
     * POST /api/login
     */
    public function login(Request $request)
    {
        // 1. Validar que vengan correo y contraseña en el formato esperado
        $validator = Validator::make($request->all(), [
            'correo'   => 'required|email',
            'password' => 'required|string|min:4',
        ], [
            'correo.required'   => 'El correo electrónico es obligatorio.',
            'correo.email'      => 'Ingresa un formato de correo válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos.',
                'errors'  => $validator->errors()
            ], 422);
        }

        // 2. Buscar al usuario por correo en la base de datos
        // Incluimos su dependencia (empresa) para tener su contexto
        $usuario = User::with('dependencia')
            ->where('correo', $request->correo)
            ->first();

        // 3. Validar si el usuario existe y si la contraseña coincide con el hash
        if (!$usuario || !Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas. Verifica tu correo y contraseña.'
            ], 401);
        }

        // 4. Validar si el usuario está activo en su empresa
        if ($usuario->estado != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta está inactiva. Contacta al administrador de tu empresa.'
            ], 403);
        }

        // 5. Actualizar la fecha del último acceso
        $usuario->ultimo_acceso = now();
        $usuario->save();

        // 6. Generar el Token criptográfico (Laravel Sanctum)
        $token = $usuario->createToken('auth_token')->plainTextToken;

        // 7. Responder a Vue con el token y los datos del usuario y su empresa
        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'data'    => [
                'token'   => $token,
                'usuario' => [
                    'idUsuario'      => $usuario->id_usuario,
                    'nombre'         => $usuario->nombre,
                    'apellidos'      => $usuario->apellidos,
                    'correo'         => $usuario->correo,
                    'rol'            => $usuario->rol,
                    'numeroEmpleado' => $usuario->numero_empleado,
                    'idDependencia'  => $usuario->id_dependencia,
                    'empresa'        => $usuario->dependencia ? [
                        'idDependencia' => $usuario->dependencia->id_dependencia,
                        'nombre'        => $usuario->dependencia->nombre,
                        'rfc'           => $usuario->dependencia->rfc,
                        'logo'          => $usuario->dependencia->logo ? (str_starts_with($usuario->dependencia->logo, 'http') ? $usuario->dependencia->logo : url($usuario->dependencia->logo)) : null,
                    ] : null
                ]
            ]
        ], 200);
    }

    /**
     * Obtener los datos del usuario actualmente autenticado por el token
     * GET /api/me
     */
    public function me(Request $request)
    {
        $usuario = $request->user()->load('dependencia');

        return response()->json([
            'success' => true,
            'data'    => [
                'usuario' => [
                    'idUsuario'      => $usuario->id_usuario,
                    'nombre'         => $usuario->nombre,
                    'apellidos'      => $usuario->apellidos,
                    'correo'         => $usuario->correo,
                    'rol'            => $usuario->rol,
                    'numeroEmpleado' => $usuario->numero_empleado,
                    'idDependencia'  => $usuario->id_dependencia,
                    'empresa'        => $usuario->dependencia ? [
                        'idDependencia' => $usuario->dependencia->id_dependencia,
                        'nombre'        => $usuario->dependencia->nombre,
                        'rfc'           => $usuario->dependencia->rfc,
                        'logo'          => $usuario->dependencia->logo ? (str_starts_with($usuario->dependencia->logo, 'http') ? $usuario->dependencia->logo : url($usuario->dependencia->logo)) : null,
                    ] : null
                ]
            ]
        ], 200);
    }

    /**
     * Cerrar sesión (revocar el token actual)
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        // Revoca el token con el que el usuario hizo esta petición
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.'
        ], 200);
    }
}
