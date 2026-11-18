<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\Dependencia;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('services.super_admin.email');
        $password = config('services.super_admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('Define SUPER_ADMIN_EMAIL y SUPER_ADMIN_PASSWORD para crear la cuenta de plataforma.');

            return;
        }

        $plataforma = Dependencia::firstOrCreate(
            ['es_plataforma' => true],
            [
                'nombre' => 'Plataforma CsRecords',
                'tipo_licencia' => 'anual',
                'fecha_expiracion' => null,
                'limite_usuarios' => 99999,
                'limite_reportes_mensuales' => 999999,
                'correo_contacto' => $email,
                'activa' => true,
            ]
        );

        Usuario::updateOrCreate(
            ['email' => $email],
            [
                'dependencia_id' => $plataforma->id,
                'numero_empleado' => 'SUPER-001',
                'rol' => RolUsuario::SuperAdmin->value,
                'nombre' => 'Super',
                'apellidos' => 'Admin',
                'password' => $password,
                'estado' => true,
            ]
        );
    }
}
