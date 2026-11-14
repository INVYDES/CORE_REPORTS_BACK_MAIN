<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Dependencia;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $identifier = trim((string) $credentials['email']);

        // Permitir inicio de sesión con correo electrónico o con ID de Empleado (ej. EMP-1001)
        $user = Usuario::where('email', $identifier)
            ->orWhereRaw('LOWER(numero_empleado) = ?', [strtolower($identifier)])
            ->first();

        // Mensaje genérico: no revelar si la cuenta existe (enumeración de usuarios).
        // Toda cuenta, sin excepción, debe validar su contraseña con Hash::check.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas. Verifica tu correo o ID de empleado y contraseña.',
                'errors' => [
                    'email' => ['Credenciales incorrectas. Verifica tu correo o ID de empleado y contraseña.'],
                ],
            ], 401);
        }

        if (! $user->estado) {
            // 403 genérico sin revelar más detalles de la cuenta
            return response()->json(['message' => 'Cuenta desactivada. Contacta al administrador.'], 403);
        }

        // Identificador del dispositivo que inicia sesión (informativo).
        $deviceId = (string) Str::uuid();
        $user->current_device = $deviceId;
        $user->forceFill(['ultimo_acceso' => now()])->save();

        // Sesiones simultáneas permitidas: no se revocan los tokens previos.
        // Cada login emite su propio token y el logout solo borra el actual.
        $token = $user->createToken($deviceId)->plainTextToken;

        return response()->json([
            'user' => $user->load('dependencia'),
            'token' => $token,
        ]);
    }

    /**
     * Registro de compañía (público). Crea dependencia + usuario admin comercial.
     * El rol del primer usuario SIEMPRE es admin comercial (rol=2): nunca se acepta del cliente.
     */
    public function registerCompania(Request $request): JsonResponse
    {
        $data = $request->validate([
            'companyName' => 'required|string|max:150',
            'rfc' => 'nullable|string|max:20',
            'fiscalRegime' => 'nullable|string|max:50',
            'cfdiUse' => 'nullable|string|max:50',
            'email' => 'required|email|unique:usuarios,email',
            'password' => ['required', Password::min(8)],
            'firstName' => 'required|string|max:100',
            'lastNamePaternal' => 'required|string|max:100',
            'lastNameMaternal' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            // Se acepta por compatibilidad con el formulario, pero el registro
            // público siempre crea un plan de prueba: los planes de pago solo
            // se activan por la pasarela (Mercado Pago).
            'planType' => 'nullable|string|max:20',
        ], [], [
            'companyName' => 'nombre de empresa',
            'email' => 'correo',
            'password' => 'contraseña',
        ]);

        $dependencia = \DB::transaction(function () use ($data) {
            $dias = 7;
            $expiracion = now()->addDays($dias)->toDateString();

            $dep = Dependencia::create([
                'nombre' => $data['companyName'],
                'rfc' => $data['rfc'] ?? null,
                'tipo_licencia' => 'trial',
                'fecha_expiracion' => $expiracion,
                'limite_usuarios' => 10,
                'limite_reportes_mensuales' => 100,
                'correo_contacto' => $data['email'],
                'telefono' => $data['phone'] ?? null,
                'activa' => true,
                // El trial del registro es el único periodo de prueba que
                // puede consumir la dependencia.
                'trial_usado_at' => now(),
            ]);

            // Suscripción inicial: deja registrado el trial para que la
            // licencia de prueba no pueda reactivarse indefinidamente.
            Suscripcion::create([
                'dependencia_id' => $dep->id,
                'proveedor' => 'registro',
                'plan' => 'trial',
                'estado' => 'trial',
                'limite_usuarios' => 10,
                'limite_reportes_mensuales' => 100,
                'fecha_inicio' => now()->toDateString(),
                'fecha_expiracion' => $expiracion,
                'renovacion_automatica' => false,
            ]);

            $user = Usuario::create([
                'dependencia_id' => $dep->id,
                'numero_empleado' => 'ADM-001',
                'rol' => 2, // Admin comercial: fijo, nunca del input
                'nombre' => $data['firstName'],
                'apellidos' => trim(($data['lastNamePaternal'] ?? '').' '.($data['lastNameMaternal'] ?? '')),
                'email' => $data['email'],
                'password' => $data['password'], // cast 'hashed' lo cifra
                'estado' => true,
            ]);

            // Generar y registrar un identificador único de dispositivo para el nuevo usuario
            $deviceId = (string) Str::uuid();
            $user->current_device = $deviceId;
            $user->save();

            return $dep;
        });

        $user = Usuario::where('email', $data['email'])->first();
        $token = $user->createToken($user->current_device)->plainTextToken;

        return response()->json([
            'dependencia' => $dependencia,
            'user' => $user->load('dependencia'),
            'token' => $token,
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->usuario($request)->load('dependencia'));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->usuario($request);
        $user->currentAccessToken()->delete();

        // Con sesiones simultáneas, current_device solo se limpia cuando
        // ya no queda ninguna sesión abierta para el usuario.
        if ($user->tokens()->count() === 0) {
            $user->current_device = null;
            $user->save();
        }

        return response()->json(['message' => 'logout']);
    }
}
