<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Dependencia;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoreReportsSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['id' => 0, 'nombre' => 'Cliente', 'codigo' => 'cliente', 'descripcion' => 'Solo crea tickets y ve los suyos'],
            ['id' => 1, 'nombre' => 'Administrador Técnico', 'codigo' => 'admin_tecnico', 'descripcion' => 'Supervisa operaciones y aprueba reportes'],
            ['id' => 2, 'nombre' => 'Administrador Comercial', 'codigo' => 'admin_comercial', 'descripcion' => 'Gestiona licencias y KPIs'],
            ['id' => 3, 'nombre' => 'Técnico', 'codigo' => 'tecnico', 'descripcion' => 'Ejecuta trabajos y genera reportes'],
        ];
        foreach ($roles as $r) {
            Role::firstOrCreate(['id' => $r['id']], $r);
        }

        $dep = Dependencia::firstOrCreate(['nombre' => 'Automotriz del Bajío S.A. de C.V.'], [
            'rfc' => 'ABA-100520-H52', 'tipo_licencia' => 'trial', 'fecha_expiracion' => '2028-05-20',
            'limite_usuarios' => 10, 'limite_reportes_mensuales' => 100,
            'correo_contacto' => 'contacto@autobajio.com', 'correo_reportes' => 'sistemas@autobajio.com',
            'telefono' => '442-123-4567', 'datos_facturacion' => 'Parque Industrial', 'activa' => true,
        ]);

        $areas = [
            ['codigo' => 'FUND', 'nombre' => 'Área de Fundición'],
            ['codigo' => 'ENS-A', 'nombre' => 'Línea de Ensamblaje A'],
            ['codigo' => 'ALM', 'nombre' => 'Almacén General'],
        ];
        foreach ($areas as $a) {
            Area::firstOrCreate(['dependencia_id' => $dep->id, 'codigo' => $a['codigo']], ['nombre' => $a['nombre'], 'activa' => true]);
        }

        $users = [
            ['numero_empleado' => 'EMP001', 'rol' => 1, 'nombre' => 'Admin', 'apellidos' => 'Tecnico', 'email' => 'admin.tec@core.com'],
            ['numero_empleado' => 'EMP002', 'rol' => 2, 'nombre' => 'Admin', 'apellidos' => 'Comercial', 'email' => 'admin.com@core.com'],
            ['numero_empleado' => 'EMP003', 'rol' => 3, 'nombre' => 'Juan', 'apellidos' => 'Tecnico', 'email' => 'tecnico@core.com'],
            ['numero_empleado' => 'EMP004', 'rol' => 0, 'nombre' => 'Cliente', 'apellidos' => 'Demo', 'email' => 'cliente@core.com'],
        ];
        foreach ($users as $u) {
            Usuario::updateOrCreate(['email' => $u['email']], array_merge($u, [
                'dependencia_id' => $dep->id, 'password' => Hash::make('password'), 'estado' => true,
            ]));
        }
    }
}
