<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Usuario;
use App\Rules\NumeroEmpleadoUnico;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class UsuarioController extends Controller
{
    use EnsuresDependencia;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user() ?? auth()->user();
        $depId = $user?->dependencia_id;

        $q = Usuario::query();
        if ($depId) {
            $q->where('dependencia_id', $depId);
        }

        if ($request->filled('rol')) {
            $q->where('rol', (int) $request->rol);
        }
        if ($request->filled('estado')) {
            $q->where('estado', $request->boolean('estado'));
        }
        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(fn ($qq) => $qq
                ->where('nombre', 'like', "%{$s}%")
                ->orWhere('apellidos', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"));
        }

        return response()->json($q->orderBy('nombre')->paginate(min(max((int) $request->get('per_page', 15), 1), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user() ?? auth('sanctum')->user() ?? auth()->user();

            if (! $user) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            if (! $user->isAdminTec() && ! $user->isAdminCom()) {
                return response()->json(['message' => 'No tienes permisos para crear usuarios.'], 403);
            }

            if (! $user->dependencia_id) {
                return response()->json(['message' => 'Tu cuenta no pertenece a ninguna empresa.'], 422);
            }

            $data = $request->validate([
                'nombre' => 'required|string|max:100',
                'apellidos' => 'required|string|max:100',
                'email' => 'required|email|unique:usuarios,email',
                'password' => ['required', 'string', 'min:6'],
                'rol' => 'required|integer|in:0,1,2,3',
                'numero_empleado' => ['nullable', 'string', 'max:50', 'regex:/^(?=.*\d)[A-Za-z0-9][A-Za-z0-9._-]{2,49}$/', new NumeroEmpleadoUnico()],
            ], ['numero_empleado.regex' => 'El ID de empleado debe empezar con letra o número, incluir al menos un número y solo usar letras, números, puntos, guiones o guiones bajos.']);

            // Obtener la dependencia de manera segura
            $depId = (int) $user->dependencia_id;
            $dep = $user->dependencia ?? Dependencia::find($depId);

            // Validar límite si la empresa tiene límite configurado
            if ($dep && ! empty($dep->limite_usuarios) && $dep->limite_usuarios > 0) {
                $conteoUsuarios = Usuario::where('dependencia_id', $depId)->count();
                if ($conteoUsuarios >= $dep->limite_usuarios) {
                    return response()->json([
                        'message' => 'Límite de usuarios alcanzado para tu empresa (' . $dep->limite_usuarios . ' máx).'
                    ], 422);
                }
            }

            // Generar número de empleado si no viene especificado
            $numeroEmpleado = ! empty($data['numero_empleado']) ? trim($data['numero_empleado']) : null;
            if (empty($numeroEmpleado)) {
                $prefix = 'EMP-' . $depId;
                $existing = Usuario::where('dependencia_id', $depId)
                    ->where('numero_empleado', 'LIKE', $prefix . '%')
                    ->pluck('numero_empleado');
                $maxNum = 0;
                foreach ($existing as $ne) {
                    $sub = substr((string) $ne, strlen($prefix));
                    $val = (int) $sub;
                    if ($val > $maxNum) {
                        $maxNum = $val;
                    }
                }
                // El ID se usa para iniciar sesión: debe ser único entre todas las
                // empresas (p. ej. EMP-1 + 1001 y EMP-11 + 001 coinciden).
                do {
                    $maxNum++;
                    $numeroEmpleado = $prefix . str_pad((string) $maxNum, 3, '0', STR_PAD_LEFT);
                } while (Usuario::withTrashed()
                    ->whereRaw('LOWER(numero_empleado) = ?', [mb_strtolower($numeroEmpleado)])
                    ->exists());
            }

            $usuario = Usuario::create([
                'nombre' => $data['nombre'],
                'apellidos' => $data['apellidos'],
                'email' => $data['email'],
                'email_verified_at' => now(),
                'password' => Hash::make($data['password']),
                'rol' => (int) $data['rol'],
                'numero_empleado' => $numeroEmpleado,
                'dependencia_id' => $depId,
                'estado' => true,
            ]);

            return response()->json($usuario, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación: ' . implode(', ', array_map(fn ($errs) => implode(' ', $errs), $e->errors())),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al crear usuario: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al crear usuario. Intenta de nuevo.',
            ], 500);
        }
    }

    public function show(Usuario $usuario): JsonResponse
    {
        $this->authorize('view', $usuario);

        return response()->json($usuario);
    }

    public function update(Request $request, Usuario $usuario): JsonResponse
    {
        try {
            $user = $request->user() ?? auth('sanctum')->user() ?? auth()->user();

            if (! $user) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            // Usuario no tiene global scope por dependencia: la policy impide
            // modificar cuentas de otras empresas.
            if ($user->cannot('update', $usuario)) {
                return response()->json(['message' => 'No tienes permisos para modificar este usuario.'], 403);
            }

            $data = $request->validate([
                'nombre' => 'sometimes|string|max:100',
                'apellidos' => 'sometimes|string|max:100',
                'email' => 'sometimes|email|unique:usuarios,email,'.$usuario->id,
                'password' => ['sometimes', 'nullable', 'string', 'min:6'],
                'rol' => 'sometimes|integer|in:0,1,2,3',
                'estado' => 'sometimes|boolean',
                'numero_empleado' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:50',
                    // El formulario reenvía el ID actual: solo se exige el formato
                    // nuevo si cambia, para no bloquear la edición de IDs antiguos.
                    function (string $attribute, mixed $value, \Closure $fail) use ($usuario): void {
                        $nuevo = trim((string) $value);
                        if ($nuevo === '' || $nuevo === trim((string) $usuario->numero_empleado)) {
                            return;
                        }
                        if (! preg_match('/^(?=.*\d)[A-Za-z0-9][A-Za-z0-9._-]{2,49}$/', $nuevo)) {
                            $fail('El ID de empleado debe empezar con letra o número, incluir al menos un número y solo usar letras, números, puntos, guiones o guiones bajos.');
                        }
                    },
                    new NumeroEmpleadoUnico($usuario->id),
                ],
            ]);

            // No se puede desactivar al último administrador de la dependencia
            if (array_key_exists('estado', $data) && $data['estado'] === false && $usuario->isAdminTec()) {
                $otrosAdmins = Usuario::where('dependencia_id', $usuario->dependencia_id)
                    ->where('rol', 1)
                    ->where('id', '!=', $usuario->id)
                    ->where('estado', true)
                    ->count();
                if ($otrosAdmins === 0) {
                    return response()->json(['message' => 'No puedes desactivar al único administrador técnico'], 422);
                }
            }

            if (array_key_exists('email', $data) && $data['email'] !== $usuario->email) {
                $data['email_verified_at'] = null;
            }

            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $usuario->update($data);

            return response()->json($usuario);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación: ' . implode(', ', array_map(fn ($errs) => implode(' ', $errs), $e->errors())),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar usuario: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error al actualizar usuario. Intenta de nuevo.',
            ], 500);
        }
    }

    public function destroy(Usuario $usuario): JsonResponse
    {
        try {
            $user = request()->user() ?? auth('sanctum')->user() ?? auth()->user();

            if (! $user) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            if ($usuario->id === $user->id) {
                return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 422);
            }

            if ($user->cannot('delete', $usuario)) {
                return response()->json(['message' => 'No tienes permisos para eliminar este usuario.'], 403);
            }

            $usuario->delete();

            return response()->json(['message' => 'deleted']);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar usuario: ' . $e->getMessage());

            return response()->json(['message' => 'Error al eliminar usuario. Intenta de nuevo.'], 500);
        }
    }
}
