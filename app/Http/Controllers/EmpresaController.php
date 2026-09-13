<?php

namespace App\Http\Controllers;

use App\Models\Dependencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmpresaController extends Controller
{
    /**
     * GET /api/empresa
     * Obtiene los datos comerciales y de configuración de la empresa (dependencia) actual
     */
    public function show(Request $request)
    {
        $idEmpresa = $request->user()->id_dependencia;
        $empresa = Dependencia::find($idEmpresa);

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatEmpresa($empresa)
        ], 200);
    }

    /**
     * PUT /api/empresa
     * Actualiza la información general de la empresa
     */
    public function update(Request $request)
    {
        $user = $request->user();
        // Solo administradores (Rol 1 o 2) pueden editar datos de la empresa
        if (!in_array((int)$user->rol, [1, 2])) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para modificar los datos de la empresa.'
            ], 403);
        }

        $idEmpresa = $user->id_dependencia;
        $empresa = Dependencia::find($idEmpresa);

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.'
            ], 404);
        }

        if ($request->has('nombre')) {
            $empresa->nombre = trim($request->input('nombre'));
        }
        if ($request->has('correoContacto') || $request->has('correo_contacto')) {
            $empresa->correo_contacto = trim($request->input('correoContacto', $request->input('correo_contacto')));
        }
        if ($request->has('correoReportes') || $request->has('correo_reportes')) {
            $empresa->correo_reportes = trim($request->input('correoReportes', $request->input('correo_reportes')));
        }
        if ($request->has('telefono')) {
            $empresa->telefono = trim($request->input('telefono'));
        }
        if ($request->has('datosFacturacion') || $request->has('datos_facturacion')) {
            $empresa->datos_facturacion = trim($request->input('datosFacturacion', $request->input('datos_facturacion')));
        }

        $empresa->save();

        return response()->json([
            'success' => true,
            'message' => 'Datos de la empresa actualizados correctamente.',
            'data'    => $this->formatEmpresa($empresa)
        ], 200);
    }

    /**
     * POST /api/empresa/logo
     * Sube y actualiza el logotipo oficial de la empresa (Multipart o Base64)
     */
    public function uploadLogo(Request $request)
    {
        $user = $request->user();
        if (!in_array((int)$user->rol, [1, 2])) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para cambiar el logotipo de la empresa.'
            ], 403);
        }

        $idEmpresa = $user->id_dependencia;
        $empresa = Dependencia::find($idEmpresa);

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.'
            ], 404);
        }

        $uploadDir = public_path('uploads/logos');
        if (!file_exists($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $urlToSave = null;

        // Opción 1: Archivo multipart tradicional
        if ($request->hasFile('logo')) {
            $validator = Validator::make($request->all(), [
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096'
            ], [
                'logo.image' => 'El archivo debe ser una imagen válida.',
                'logo.max'   => 'La imagen no debe superar los 4 MB.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $file = $request->file('logo');
            $fileName = 'logo_empresa_' . $idEmpresa . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $fileName);
            $urlToSave = '/uploads/logos/' . $fileName;
        } 
        // Opción 2: Cadena Base64 enviada en JSON
        elseif ($request->filled('logo')) {
            $logoStr = (string) $request->input('logo');
            if (preg_match('/^data:image\/(\w+);base64,/', $logoStr, $matches)) {
                $imageType = strtolower($matches[1]);
                $base64Data = substr($logoStr, strpos($logoStr, ',') + 1);
                $decodedData = base64_decode($base64Data);

                if ($decodedData !== false) {
                    $ext = ($imageType === 'jpeg') ? 'jpg' : $imageType;
                    $fileName = 'logo_empresa_' . $idEmpresa . '_' . uniqid() . '.' . $ext;
                    file_put_contents($uploadDir . DIRECTORY_SEPARATOR . $fileName, $decodedData);
                    $urlToSave = '/uploads/logos/' . $fileName;
                }
            } elseif (str_starts_with($logoStr, '/uploads/logos/')) {
                $urlToSave = $logoStr;
            }
        }

        if (!$urlToSave) {
            return response()->json([
                'success' => false,
                'message' => 'No se proporcionó una imagen válida para el logotipo.'
            ], 422);
        }

        $empresa->logo = $urlToSave;
        $empresa->save();

        return response()->json([
            'success' => true,
            'message' => 'Logotipo de la empresa actualizado correctamente.',
            'data'    => $this->formatEmpresa($empresa)
        ], 200);
    }

    /**
     * Formateador auxiliar
     */
    private function formatEmpresa(Dependencia $e): array
    {
        $logoUrl = null;
        if ($e->logo) {
            $logoUrl = str_starts_with($e->logo, 'http') ? $e->logo : url($e->logo);
        }

        return [
            'idDependencia'           => $e->id_dependencia,
            'nombre'                  => $e->nombre,
            'rfc'                     => $e->rfc,
            'tipoLicencia'            => $e->tipo_licencia,
            'fechaExpiracionLicencia' => $e->fecha_expiracion_licencia ? date('Y-m-d', strtotime($e->fecha_expiracion_licencia)) : null,
            'correoContacto'          => $e->correo_contacto,
            'correoReportes'          => $e->correo_reportes,
            'telefono'                => $e->telefono,
            'datosFacturacion'        => $e->datos_facturacion,
            'activo'                  => (bool) $e->activo,
            'fechaRegistro'           => $e->fecha_registro ? date('Y-m-d', strtotime($e->fecha_registro)) : null,
            'nivelUsuarios'           => $e->nivel_usuarios,
            'limiteUsuarios'          => (int) $e->limite_usuarios,
            'logo'                    => $logoUrl,
        ];
    }
}
