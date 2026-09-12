<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Traits\EnsuresDependencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UsuarioController extends Controller
{
    use EnsuresDependencia;

    public function index(Request $request): JsonResponse
    {
        $q = Usuario::query();

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

        return response()->json($q->orderBy('nombre')->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Usuario::class);

        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'email' => 'required|email|unique:usuarios,email',
            'password' => ['required', Password::min(8)],
            'rol' => 'required|integer|in:0,1,2,3',
            'numero_empleado' => 'nullable|string|max:50',
        ]);

        $dep = $request->user()->dependencia;

        if (Usuario::where('dependencia_id', $dep->id)->count() >= $dep->limite_usuarios) {
            return response()->json(['message' => 'Límite de usuarios alcanzado'], 422);
        }

        $usuario = Usuario::create([
            ...$data,
            'password' => $data['password'], // cast 'hashed' lo cifra
            'dependencia_id' => $dep->id,
            'estado' => true,
        ]);

        return response()->json($usuario, 201);
    }

    public function show(Usuario $usuario): JsonResponse
    {
        $this->authorize('view', $usuario);

        return response()->json($usuario);
    }

    public function update(Request $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('update', $usuario);

        $data = $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'apellidos' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:usuarios,email,'.$usuario->id,
            'password' => ['sometimes', 'nullable', Password::min(8)],
            'rol' => 'sometimes|integer|in:0,1,2,3',
            'estado' => 'sometimes|boolean',
            'numero_empleado' => 'sometimes|nullable|string|max:50',
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
            $data['password'] = $data['password']; // cast 'hashed' lo cifra
        } else {
            unset($data['password']);
        }

        $usuario->update($data);

        return response()->json($usuario);
    }

    public function destroy(Usuario $usuario): JsonResponse
    {
        $this->authorize('delete', $usuario);
        $usuario->delete();

        return response()->json(['message' => 'deleted']);
    }
}
