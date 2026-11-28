<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Dependencia;
use App\Models\Licencia;
use App\Models\Usuario;
use App\Rules\NumeroEmpleadoUnico;
use App\Services\CorreoAutomatizadoService;
use App\Services\LicenciaService;
use App\Services\VerificacionCorreoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private LicenciaService $licencias,
        private VerificacionCorreoService $verificaciones,
        private CorreoAutomatizadoService $correos,
    ) {
    }

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

        if (config('correo.verificacion_obligatoria') && ! $user->correoVerificado()) {
            $resultado = $this->verificaciones->solicitar($user, $request, true);

            return response()->json([
                'requiere_verificacion' => true,
                'email' => $user->email,
                'message' => $resultado['enviado']
                    ? 'Enviamos un código y un enlace de verificación a tu correo.'
                    : 'Tu correo aún no está verificado. Revisa tu bandeja o solicita un nuevo código.',
                'correo_enviado' => (bool) ($resultado['enviado'] ?? false),
                'expira_en' => isset($resultado['expira']) ? $resultado['expira']->toIso8601String() : null,
                'reenviar_en_segundos' => (int) ($resultado['reenviar_en_segundos'] ?? 0),
            ]);
        }

        return $this->respuestaSesion($user);
    }

    private function respuestaSesion(Usuario $user): JsonResponse
    {
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
            'password' => ['required', PasswordRule::min(8)],
            'firstName' => 'required|string|max:100',
            'lastNamePaternal' => 'required|string|max:100',
            'lastNameMaternal' => 'nullable|string|max:100',
            // Mismo formato que acepta el login por ID de empleado.
            'numeroEmpleado' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^(?=.*\d)[A-Za-z0-9][A-Za-z0-9._-]{2,49}$/',
                new NumeroEmpleadoUnico(),
            ],
            'phone' => 'nullable|string|max:30',
            'street' => 'nullable|string|max:150',
            'colony' => 'nullable|string|max:100',
            'zipCode' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'paymentMethod' => 'nullable|string|max:10',
            'paymentForm' => 'nullable|string|max:10',
            'licenciaToken' => 'nullable|string|max:500',
            'licenciaId' => 'nullable|integer|min:1',
            // Se conserva por compatibilidad con clientes anteriores.
            'planType' => 'nullable|string|max:20',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no debe tener más de :max caracteres.',
            'email' => 'El campo :attribute debe ser un correo electrónico válido.',
            'unique' => 'El campo :attribute ya está registrado.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'min' => 'El campo :attribute debe ser como mínimo :min.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.string' => 'La contraseña debe ser texto.',
            'numeroEmpleado.regex' => 'El ID de empleado debe empezar con letra o número, incluir al menos un número y solo usar letras, números, puntos, guiones o guiones bajos.',
        ], [
            'companyName' => 'nombre de empresa',
            'email' => 'correo',
            'password' => 'contraseña',
            'numeroEmpleado' => 'ID de empleado',
            'rfc' => 'RFC',
            'fiscalRegime' => 'régimen fiscal',
            'cfdiUse' => 'uso de CFDI',
            'firstName' => 'nombre(s)',
            'lastNamePaternal' => 'apellido paterno',
            'lastNameMaternal' => 'apellido materno',
            'phone' => 'teléfono',
            'street' => 'calle y número',
            'colony' => 'colonia',
            'zipCode' => 'código postal',
            'city' => 'ciudad',
            'state' => 'estado',
            'municipality' => 'municipio/alcaldía',
            'paymentMethod' => 'método de pago',
            'paymentForm' => 'forma de pago',
            'licenciaToken' => 'plan',
            'licenciaId' => 'plan',
            'planType' => 'plan',
        ]);

        // La regla NumeroEmpleadoUnico ya validó la unicidad sin distinguir mayúsculas.
        $numeroEmpleado = trim((string) ($data['numeroEmpleado'] ?? ''));

        if (! empty($data['licenciaToken'])) {
            $licencia = $this->licencias->resolverTokenLicencia($data['licenciaToken']);

            if (! $licencia instanceof Licencia) {
                return response()->json([
                    'message' => 'El plan seleccionado no es válido o venció. Recarga la página e intenta de nuevo.',
                ], 422);
            }
        } else {
            $licencia = $this->licencias->resolverLicencia((int) ($data['licenciaId'] ?? 1));
            if ($licencia instanceof Licencia && $licencia->exists && ! $licencia->activo) {
                $licencia = null;
            }
        }

        if (! $licencia instanceof Licencia) {
            return response()->json([
                'message' => 'El plan seleccionado no está disponible.',
            ], 422);
        }

        $esGratuito = $this->licencias->esPlanGratuito($licencia);
        $tipoDependencia = in_array($licencia->tipo, ['mensual', 'anual'], true)
            ? $licencia->tipo
            : 'mensual';
        $maxUsuarios = (int) ($licencia->max_usuarios ?? 2);
        $maxReportes = (int) ($licencia->max_reportes_mensuales ?? 999999);

        $dependencia = DB::transaction(function () use ($data, $licencia, $esGratuito, $tipoDependencia, $maxUsuarios, $maxReportes, $numeroEmpleado) {
            $dep = Dependencia::create([
                'nombre' => $data['companyName'],
                'rfc' => $data['rfc'] ?? null,
                'tipo_licencia' => $esGratuito ? 'trial' : $tipoDependencia,
                'fecha_expiracion' => null,
                'limite_usuarios' => $maxUsuarios,
                'limite_reportes_mensuales' => $maxReportes,
                'correo_contacto' => $data['email'],
                'telefono' => $data['phone'] ?? null,
                'regimen_fiscal' => $data['fiscalRegime'] ?? null,
                'uso_cfdi' => $data['cfdiUse'] ?? null,
                'calle' => $data['street'] ?? null,
                'colonia' => $data['colony'] ?? null,
                'codigo_postal' => $data['zipCode'] ?? null,
                'ciudad' => $data['city'] ?? null,
                'estado' => $data['state'] ?? null,
                'municipio' => $data['municipality'] ?? null,
                'metodo_pago' => $data['paymentMethod'] ?? null,
                'forma_pago' => $data['paymentForm'] ?? null,
                'activa' => true,
                // El plan gratuito no consume el mes de prueba de los planes de pago.
                'trial_usado_at' => null,
            ]);

            if ($esGratuito) {
                $this->licencias->activarLicencia($dep, $licencia, 'registro', 0);
            } else {
                $this->licencias->iniciarPruebaLicencia($dep, $licencia, 'registro');
            }

            $empleadoId = $numeroEmpleado;
            if ($empleadoId === '') {
                $baseId = 'ADM-'.$dep->id;
                $empleadoId = $baseId;
                $sufijo = 2;
                while (Usuario::withTrashed()->whereRaw('LOWER(numero_empleado) = ?', [mb_strtolower($empleadoId)])->exists()) {
                    $empleadoId = $baseId.'-'.$sufijo;
                    $sufijo++;
                }
            }

            $user = Usuario::create([
                'dependencia_id' => $dep->id,
                // Si el cliente no envía ID se genera uno derivado del id de la empresa.
                'numero_empleado' => $empleadoId,
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

        $user = Usuario::where('email', $data['email'])->firstOrFail();

        $this->correos->enviarBienvenida($user, $licencia);

        $payload = [
            'dependencia' => $dependencia,
            'user' => $user->load('dependencia'),
            'requiere_pago' => ! $esGratuito,
            'licencia' => $this->formatearLicencia($licencia),
        ];

        if (config('correo.verificacion_obligatoria')) {
            $verificacion = $this->verificaciones->solicitar($user, $request);

            return response()->json([
                ...$payload,
                'requiere_verificacion' => true,
                'correo_enviado' => (bool) ($verificacion['enviado'] ?? false),
                'expira_en' => isset($verificacion['expira']) ? $verificacion['expira']->toIso8601String() : null,
                'reenviar_en_segundos' => (int) ($verificacion['reenviar_en_segundos'] ?? 0),
            ], 201);
        }

        $token = $user->createToken($user->current_device)->plainTextToken;

        return response()->json([
            ...$payload,
            'token' => $token,
        ], 201);
    }

    private function formatearLicencia(Licencia $licencia): array
    {
        return [
            'id' => (int) $licencia->id,
            'token' => $this->licencias->tokenLicencia($licencia),
            'nombre' => $licencia->nombre,
            'codigo' => $licencia->codigo,
            'tipo' => $licencia->tipo,
            'max_usuarios' => (int) $licencia->max_usuarios,
            'max_reportes_mensuales' => (int) $licencia->max_reportes_mensuales,
            'precio' => (float) $licencia->precio,
            'precio_anual' => $licencia->precio_anual !== null ? (float) $licencia->precio_anual : null,
            'dias_prueba' => (int) $licencia->dias_prueba,
            'descripcion' => $licencia->descripcion,
        ];
    }

    public function verificarCodigo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'codigo' => 'required|digits:6',
        ]);

        $resultado = $this->verificaciones->verificarCodigo($data['email'], $data['codigo']);

        if (isset($resultado['error'])) {
            $mensajes = [
                'codigo' => 'El código no es válido o ya venció. Solicita uno nuevo.',
                'expirado' => 'El código venció. Solicita uno nuevo.',
                'intentos' => 'Demasiados intentos con este código. Solicita uno nuevo.',
            ];
            $mensaje = $mensajes[$resultado['error']] ?? 'No se pudo verificar el correo.';

            return response()->json([
                'message' => $mensaje,
                'errors' => ['codigo' => [$mensaje]],
                'intentos_restantes' => $resultado['intentos_restantes'] ?? null,
            ], $resultado['error'] === 'intentos' ? 429 : 422);
        }

        return $this->respuestaSesion($resultado['usuario']);
    }

    public function verificarEnlace(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:200',
        ]);

        $usuario = $this->verificaciones->verificarToken($data['token']);

        if (! $usuario) {
            return response()->json([
                'message' => 'El enlace de verificación no es válido o venció. Solicita uno nuevo.',
            ], 422);
        }

        if (! $usuario->estado) {
            return response()->json(['message' => 'Cuenta desactivada. Contacta al administrador.'], 403);
        }

        return $this->respuestaSesion($usuario);
    }

    public function reenviarVerificacion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $usuario = Usuario::where('email', $data['email'])->first();

        if (! $usuario || $usuario->correoVerificado() || ! $usuario->estado) {
            return response()->json([
                'message' => 'Si la cuenta existe y requiere verificación, enviaremos un nuevo correo.',
                'correo_enviado' => false,
            ]);
        }

        $resultado = $this->verificaciones->solicitar($usuario, $request, true);

        if (($resultado['motivo'] ?? null) === 'espera') {
            return response()->json([
                'message' => 'Espera un momento antes de solicitar otro correo.',
                'correo_enviado' => false,
                'reenviar_en_segundos' => (int) ($resultado['reenviar_en_segundos'] ?? 0),
            ], 429);
        }

        return response()->json([
            'message' => $resultado['enviado']
                ? 'Enviamos un nuevo código y enlace de verificación.'
                : 'No pudimos enviar el correo en este momento. Intenta de nuevo más tarde.',
            'correo_enviado' => (bool) $resultado['enviado'],
            'expira_en' => isset($resultado['expira']) ? $resultado['expira']->toIso8601String() : null,
            'reenviar_en_segundos' => (int) ($resultado['reenviar_en_segundos'] ?? 0),
        ]);
    }

    public function olvidePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $usuario = Usuario::where('email', $data['email'])->first();

        if ($usuario && $usuario->estado) {
            $token = Password::broker()->createToken($usuario);
            $enlace = rtrim((string) config('correo.frontend_url'), '/')
                .'/reset-password?token='.urlencode($token)
                .'&email='.urlencode($usuario->email);

            $this->correos->enviarRecuperacion(
                $usuario,
                $enlace,
                (int) config('auth.passwords.users.expire', 60),
            );
        }

        return response()->json([
            'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        try {
            $estado = Password::broker()->reset([
                'email' => $data['email'],
                'token' => $data['token'],
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ], function (Usuario $usuario, string $password): void {
                $usuario->forceFill([
                    'password' => $password,
                    'email_verified_at' => $usuario->email_verified_at ?? now(),
                ])->save();

                $usuario->tokens()->delete();
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'No se pudo restablecer la contraseña.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($estado !== \Illuminate\Auth\Passwords\PasswordBroker::PASSWORD_RESET) {
            return response()->json([
                'message' => __($estado),
                'errors' => ['email' => [__($estado)]],
            ], 422);
        }

        return response()->json([
            'message' => 'Contraseña actualizada. Ya puedes iniciar sesión.',
        ]);
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
