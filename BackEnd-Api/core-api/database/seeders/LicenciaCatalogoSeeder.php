<?php

namespace Database\Seeders;

use App\Models\Licencia;
use Illuminate\Database\Seeder;

class LicenciaCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $planes = [
            [
                'codigo' => 'trial',
                'nombre' => 'Gratis',
                'tipo' => 'trial',
                'max_usuarios' => 2,
                'max_reportes_mensuales' => 999999,
                'precio' => 0,
                'precio_anual' => null,
                'dias_prueba' => 0,
                'descripcion' => 'Plan gratuito para 2 usuarios, sin fecha de expiración',
            ],
            [
                'codigo' => 'usuarios_5_mensual',
                'nombre' => '5 usuarios - Mensual',
                'tipo' => 'mensual',
                'max_usuarios' => 5,
                'max_reportes_mensuales' => 999999,
                'precio' => 399,
                'precio_anual' => null,
                'dias_prueba' => 30,
                'descripcion' => '5 usuarios - $399 MXN al mes · incluye 1 mes gratis',
            ],
            [
                'codigo' => 'usuarios_5_anual',
                'nombre' => '5 usuarios - Anual',
                'tipo' => 'anual',
                'max_usuarios' => 5,
                'max_reportes_mensuales' => 999999,
                'precio' => 399,
                'precio_anual' => 3990,
                'dias_prueba' => 30,
                'descripcion' => '5 usuarios - $3,990 MXN al año · incluye 1 mes gratis',
            ],
            [
                'codigo' => 'usuarios_10_mensual',
                'nombre' => '10 usuarios - Mensual',
                'tipo' => 'mensual',
                'max_usuarios' => 10,
                'max_reportes_mensuales' => 999999,
                'precio' => 699,
                'precio_anual' => null,
                'dias_prueba' => 30,
                'descripcion' => '10 usuarios - $699 MXN al mes · incluye 1 mes gratis',
            ],
            [
                'codigo' => 'usuarios_10_anual',
                'nombre' => '10 usuarios - Anual',
                'tipo' => 'anual',
                'max_usuarios' => 10,
                'max_reportes_mensuales' => 999999,
                'precio' => 699,
                'precio_anual' => 6990,
                'dias_prueba' => 30,
                'descripcion' => '10 usuarios - $6,990 MXN al año · incluye 1 mes gratis',
            ],
            [
                'codigo' => 'usuarios_30_mensual',
                'nombre' => '30 usuarios - Mensual',
                'tipo' => 'mensual',
                'max_usuarios' => 30,
                'max_reportes_mensuales' => 999999,
                'precio' => 1999,
                'precio_anual' => null,
                'dias_prueba' => 30,
                'descripcion' => '30 usuarios - $1,999 MXN al mes · incluye 1 mes gratis',
            ],
            [
                'codigo' => 'usuarios_30_anual',
                'nombre' => '30 usuarios - Anual',
                'tipo' => 'anual',
                'max_usuarios' => 30,
                'max_reportes_mensuales' => 999999,
                'precio' => 1999,
                'precio_anual' => 19990,
                'dias_prueba' => 30,
                'descripcion' => '30 usuarios - $19,990 MXN al año · incluye 1 mes gratis',
            ],
        ];

        foreach ($planes as $plan) {
            Licencia::updateOrCreate(
                ['codigo' => $plan['codigo']],
                array_merge($plan, ['activo' => true])
            );
        }
    }
}
