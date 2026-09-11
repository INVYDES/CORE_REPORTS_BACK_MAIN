<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function index(Request $r){
        return Usuario::where('dependencia_id',$r->user()->dependencia_id)->paginate($r->get('per_page',15));
    }
    public function store(Request $r){
        $data=$r->validate(['nombre'=>'required','apellidos'=>'required','email'=>'required|email|unique:usuarios,email','password'=>'required|min:6','rol'=>'required|in:0,1,2,3','numero_empleado'=>'nullable']);
        $dep=$r->user()->dependencia;
        if(Usuario::where('dependencia_id',$dep->id)->count() >= $dep->limite_usuarios) return response()->json(['message'=>'Límite alcanzado'],422);
        $data['dependencia_id']=$dep->id; $data['password']=Hash::make($data['password']);
        return response()->json(Usuario::create($data),201);
    }
    public function show(Usuario $usuario){ $this->authorizeDependencia($usuario); return $usuario; }
    public function update(Request $r, Usuario $usuario){
        $this->authorizeDependencia($usuario);
        $data=$r->validate(['nombre'=>'sometimes','apellidos'=>'sometimes','email'=>'sometimes|email|unique:usuarios,email,'.$usuario->id,'rol'=>'sometimes|in:0,1,2,3','estado'=>'sometimes|boolean','numero_empleado'=>'sometimes|nullable']);
        if(isset($data['email'])) $data['email_verified_at']=null;
        $usuario->update($data); return $usuario;
    }
    public function destroy(Usuario $usuario){ $this->authorizeDependencia($usuario); $usuario->delete(); return response()->json(['message'=>'deleted']); }
    private function authorizeDependencia(Usuario $u){ if($u->dependencia_id !== auth()->user()->dependencia_id) abort(403); }
}
