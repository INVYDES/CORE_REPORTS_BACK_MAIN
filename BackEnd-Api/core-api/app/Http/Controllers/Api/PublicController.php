<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function registerCompania(Request $request)
    {
        $data = $request->validate([
            'companyName' => 'required|string|max:150',
            'rfc' => 'nullable|string|max:20',
            'fiscalRegime' => 'nullable|string',
            'cfdiUse' => 'nullable|string',
            'email' => 'required|email|unique:usuarios,email',
            'password' => 'required|min:6',
            'firstName' => 'required|string',
            'lastNamePaternal' => 'required|string',
            'lastNameMaternal' => 'nullable|string',
            'phone' => 'nullable|string',
            'planType' => 'nullable|string',
        ], [], [
            'companyName' => 'nombre de empresa',
            'email' => 'correo',
            'password' => 'contraseña',
        ]);

        return DB::transaction(function () use ($data) {
            $tipoMap = ['free'=>'trial','monthly'=>'mensual','annual'=>'anual','trial'=>'trial','mensual'=>'mensual','anual'=>'anual'];
            $tipo = $tipoMap[$data['planType'] ?? ''] ?? 'trial';
            $dep = Dependencia::create([
                'nombre' => $data['companyName'],
                'rfc' => $data['rfc'] ?? null,
                'tipo_licencia' => $tipo,
                'fecha_expiracion' => $tipo==='trial' ? now()->addDays(7) : ($tipo==='mensual' ? now()->addMonth() : now()->addYear()),
                'limite_usuarios' => 10,
                'limite_reportes_mensuales' => 100,
                'correo_contacto' => $data['email'],
                'telefono' => $data['phone'] ?? null,
                'activa' => true,
            ]);
            $user = Usuario::create([
                'dependencia_id' => $dep->id,
                'numero_empleado' => 'ADM-001',
                'rol' => 1,
                'nombre' => $data['firstName'],
                'apellidos' => trim(($data['lastNamePaternal'] ?? '').' '.($data['lastNameMaternal'] ?? '')),
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'estado' => true,
            ]);
            $token = $user->createToken('api')->plainTextToken;
            return response()->json(['dependencia'=>$dep,'user'=>$user->load('dependencia'),'token'=>$token],201);
        });
    }
}
