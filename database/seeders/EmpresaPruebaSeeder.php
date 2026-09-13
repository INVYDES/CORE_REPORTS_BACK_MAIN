<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Dependencia;
use App\Models\Subdependencia;
use App\Models\User;
use App\Models\Ticket;
use App\Models\ServicioProgramado;

class EmpresaPruebaSeeder extends Seeder
{
    /**
     * Ejecuta el seeder para crear una segunda dependencia con usuarios y datos de prueba.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Crear la Dependencia (Empresa)
            $empresa = Dependencia::create([
                'nombre'                    => 'Soluciones Industriales del Norte S.A. de C.V.',
                'rfc'                       => 'SIN240101AA1',
                'tipo_licencia'             => 'Mensual',
                'fecha_expiracion_licencia' => now()->addYear()->toDateString(),
                'correo_contacto'           => 'contacto@industriasnorte.com',
                'correo_reportes'           => 'reportes@industriasnorte.com',
                'telefono'                  => '8181234567',
                'datos_facturacion'         => 'Av. Fundidora 500, Monterrey, N.L. C.P. 64010',
                'activo'                    => 1,
                'fecha_registro'            => now()->toDateString(),
                'nivel_usuarios'            => 'Ilimitado',
                'limite_usuarios'           => 20,
                'logo'                      => null,
            ]);

            $idDep = $empresa->id_dependencia;

            // 2. Crear Subdependencias (Áreas)
            $area1 = Subdependencia::create([
                'id_dependencia' => $idDep,
                'nombre'         => 'Planta de Ensamble',
                'activo'         => 1,
            ]);

            $area2 = Subdependencia::create([
                'id_dependencia' => $idDep,
                'nombre'         => 'Almacén Central',
                'activo'         => 1,
            ]);

            $area3 = Subdependencia::create([
                'id_dependencia' => $idDep,
                'nombre'         => 'Área de Subestación y Calderas',
                'activo'         => 1,
            ]);

            // 3. Crear Usuarios para esta empresa (Contraseña para todos: password123)
            $passwordHash = Hash::make('password123');

            // Admin Técnico (Rol 1)
            $adminTecnico = User::create([
                'id_dependencia'  => $idDep,
                'numero_empleado' => 'EMP-NORTE-01',
                'rol'             => 1,
                'nombre'          => 'Carlos',
                'apellidos'       => 'Mendoza',
                'correo'          => 'admin.norte@core.com',
                'password'        => $passwordHash,
                'estado'          => 1,
            ]);

            // Administrador Comercial (Rol 2)
            $adminComercial = User::create([
                'id_dependencia'  => $idDep,
                'numero_empleado' => 'EMP-NORTE-02',
                'rol'             => 2,
                'nombre'          => 'Mariana',
                'apellidos'       => 'López',
                'correo'          => 'gerencia.norte@core.com',
                'password'        => $passwordHash,
                'estado'          => 1,
            ]);

            // Técnico Operativo (Rol 3)
            $tecnico = User::create([
                'id_dependencia'  => $idDep,
                'numero_empleado' => 'EMP-NORTE-03',
                'rol'             => 3,
                'nombre'          => 'Roberto',
                'apellidos'       => 'Garza',
                'correo'          => 'tecnico.norte@core.com',
                'password'        => $passwordHash,
                'estado'          => 1,
            ]);

            // 4. Crear Ticket de prueba asignado al técnico Roberto Garza
            Ticket::create([
                'folio'              => 'TCK-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)),
                'id_dependencia'     => $idDep,
                'id_subdependencia'  => $area1->id_subdependencia,
                'asunto'             => 'Falla en compresor de aire #2',
                'descripcion'        => 'El compresor presenta fuga de presión constante y vibración excesiva.',
                'solicitante'        => 'Ing. Fernando Castro',
                'cargo'              => 'Supervisor de Turno',
                'prioridad'          => 'Alta',
                'estatus'            => 'En Proceso',
                'fecha_solicitud'    => now()->toDateString(),
                'fecha_atencion'     => now()->toDateString(),
                'atendio'            => 'Roberto Garza',
                'id_tecnico_atendio' => $tecnico->id_usuario,
            ]);

            // 5. Crear Servicio Programado de prueba asignado a Roberto Garza
            ServicioProgramado::create([
                'folio'               => 'SVC-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)),
                'id_dependencia'      => $idDep,
                'id_subdependencia'   => $area3->id_subdependencia,
                'asunto'              => 'Mantenimiento Preventivo Trimestral Subestación',
                'descripcion'         => 'Revisión y apriete de terminales, termografía y limpieza de tablero principal.',
                'solicitante'         => 'Mariana López',
                'cargo'               => 'Gerente de Planta',
                'id_tecnico_asignado' => $tecnico->id_usuario,
                'asignado_a'          => 'Roberto Garza',
                'fecha_asignacion'    => now()->toDateString(),
                'fecha_vencimiento'   => now()->addDays(5)->toDateString(),
                'estatus'             => 'Programado',
                'prioridad'           => 'Media',
            ]);
        });
    }
}
