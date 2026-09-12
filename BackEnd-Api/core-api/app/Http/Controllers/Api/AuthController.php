<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


use Illuminate\Support\Str;


class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = Usuario::where('email', $credentials['email'])->first();

        // Mensaje genérico: no revelar si el correo existe (enumeración de usuarios)
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas. Verifica tu correo y contraseña.',
                'errors' => [
                    'email' => ['Credenciales incorrectas. Verifica tu correo y contraseña.'],
                ],
            ], 401);
        }

        // Generar y registrar un identificador único de dispositivo
        $deviceId = (string) Str::uuid();
        $user->current_device = $deviceId;
        $user->save();

        if (! $user->estado) {
            // 403 genérico sin revelar más detalles de la cuenta
            return response()->json(['message' => 'Cuenta desactivada. Contacta al administrador.'], 403);
        }

        $user->forceFill(['ultimo_acceso' => now()])->save();

        // Revocar tokens anteriores: una sesión activa por usuario
        $user->tokens()->delete();

        $token = $user->createToken($user->current_device)->plainTextToken;

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
            'planType' => 'nullable|in:free,trial,monthly,annual,mensual,anual',
        ], [], [
            'companyName' => 'nombre de empresa',
            'email' => 'correo',
            'password' => 'contraseña',
        ]);

        $dependencia = \DB::transaction(function () use ($data) {
            $tipoMap = [
                'free' => 'trial',
                'trial' => 'trial',
                'monthly' => 'mensual',
                'mensual' => 'mensual',
                'annual' => 'anual',
                'anual' => 'anual',
            ];
            $tipo = $tipoMap[$data['planType'] ?? ''] ?? 'trial';

            $dias = match ($tipo) {
                'trial' => 7,
                'mensual' => 30,
                'anual' => 365,
            };

            $dep = Dependencia::create([
                'nombre' => $data['companyName'],
                'rfc' => $data['rfc'] ?? null,
                'tipo_licencia' => $tipo,
                'fecha_expiracion' => now()->addDays($dias)->toDateString(),
                'limite_usuarios' => 10,
                'limite_reportes_mensuales' => 100,
                'correo_contacto' => $data['email'],
                'telefono' => $data['phone'] ?? null,
                'activa' => true,
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
        return response()->json($request->user()->load('dependencia'));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->current_device = null;
            $user->save();
        }
        $request->user()->currentAccessToken()->delete();


        return response()->json(['message' => 'logout']);
    }
}
