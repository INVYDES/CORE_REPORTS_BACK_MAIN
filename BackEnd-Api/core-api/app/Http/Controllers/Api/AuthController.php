<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        Log::info('Login attempt', ['ip'=>$request->ip(), 'headers'=>$request->headers->all(), 'content'=>$request->getContent(), 'all'=>$request->all()]);
        $request->validate(
            ['email'=>'required|email','password'=>'required'],
            ['email.required'=>'El correo es obligatorio','email.email'=>'El correo no es válido','password.required'=>'La contraseña es obligatoria']
        );
        $user = Usuario::where('email',$request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email'=>['Credenciales incorrectas. Verifica tu correo y contraseña.']]);
        }
        if (!$user->estado) return response()->json(['message'=>'Usuario desactivado. Contacta al administrador.'],403);
        $user->update(['ultimo_acceso'=>now()]);
        $token = $user->createToken('api')->plainTextToken;
        return response()->json(['user'=>$user->load('dependencia'),'token'=>$token]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'nombre'=>'required|string|max:100','apellidos'=>'required|string|max:100',
            'email'=>'required|email|unique:usuarios,email','password'=>'required|min:6',
            'dependencia_id'=>'required|exists:dependencias,id','rol'=>'required|integer|in:0,1,2,3',
            'numero_empleado'=>'nullable|string'
        ]);
        $dependencia = \App\Models\Dependencia::findOrFail($data['dependencia_id']);
        $count = Usuario::where('dependencia_id',$dependencia->id)->count();
        if ($count >= $dependencia->limite_usuarios) {
            return response()->json(['message'=>'Límite de usuarios alcanzado para esta dependencia'],422);
        }
        $data['password'] = Hash::make($data['password']);
        $user = Usuario::create($data);
        $token = $user->createToken('api')->plainTextToken;
        return response()->json(['user'=>$user,'token'=>$token],201);
    }

    public function me(Request $request){ return response()->json($request->user()->load('dependencia')); }
    public function logout(Request $request){ $request->user()->currentAccessToken()->delete(); return response()->json(['message'=>'logout']); }
}
