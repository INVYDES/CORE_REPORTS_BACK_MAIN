<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Licencia;
use App\Models\Usuario;
use Database\Seeders\LicenciaCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeguridadLicenciasTest extends TestCase
{
    use RefreshDatabase;

    private function dependencia(array $extra = []): Dependencia
    {
        return Dependencia::create(array_merge([
            'nombre' => 'Dep Licencias',
            'tipo_licencia' => 'trial',
            'fecha_expiracion' => now()->addDays(5),
            'limite_usuarios' => 10,
            'limite_reportes_mensuales' => 100,
            'correo_reportes' => 'reportes@licencias.com',
            'activa' => true,
        ], $extra));
    }

    private function usuario(Dependencia $dep, int $rol = 1, string $email = 'admin@licencias.com'): Usuario
    {
        return Usuario::create([
            'dependencia_id' => $dep->id,
            'rol' => $rol,
            'nombre' => 'Admin',
            'apellidos' => 'Licencias',
            'email' => $email,
            'password' => 'password123',
            'estado' => true,
        ]);
    }

    public function test_registro_publico_crea_plan_gratuito_sin_expiracion(): void
    {
        $this->postJson('/api/v1/public/register-compania', [
            'companyName' => 'Acme Industrial',
            'email' => 'nuevo@acme.com',
            'password' => 'password123',
            'firstName' => 'Ana',
            'lastNamePaternal' => 'Pérez',
            'licenciaId' => 1,
        ])
            ->assertStatus(201)
            ->assertJsonPath('requiere_pago', false)
            ->assertJsonPath('licencia.codigo', 'trial');

        $dep = Dependencia::where('nombre', 'Acme Industrial')->firstOrFail();

        $this->assertSame('trial', $dep->tipo_licencia);
        $this->assertNull($dep->fecha_expiracion, 'El plan gratuito no debe expirar');
        $this->assertNull($dep->trial_usado_at, 'El plan gratuito no consume el mes de prueba');

        $this->assertDatabaseHas('suscripciones', [
            'dependencia_id' => $dep->id,
            'plan' => 'gratis',
            'estado' => 'activa',
            'fecha_expiracion' => null,
        ]);
    }

    public function test_registro_publico_guarda_datos_fiscales_y_de_pago(): void
    {
        $this->postJson('/api/v1/public/register-compania', [
            'companyName' => 'Acme Fiscal',
            'email' => 'fiscal@acme.com',
            'password' => 'password123',
            'firstName' => 'Ana',
            'lastNamePaternal' => 'Pérez',
            'rfc' => 'XAXX010101000',
            'fiscalRegime' => '601',
            'cfdiUse' => 'G03',
            'street' => 'Av. Reforma 123',
            'colony' => 'Centro',
            'zipCode' => '06000',
            'city' => 'CDMX',
            'state' => 'CDMX',
            'municipality' => 'Cuauhtémoc',
            'paymentMethod' => 'PUE',
            'paymentForm' => '03',
            'licenciaId' => 1,
        ])->assertStatus(201);

        $dep = Dependencia::where('nombre', 'Acme Fiscal')->firstOrFail();

        $this->assertSame('XAXX010101000', $dep->rfc);
        $this->assertSame('601', $dep->regimen_fiscal);
        $this->assertSame('G03', $dep->uso_cfdi);
        $this->assertSame('Av. Reforma 123', $dep->calle);
        $this->assertSame('Centro', $dep->colonia);
        $this->assertSame('06000', $dep->codigo_postal);
        $this->assertSame('CDMX', $dep->ciudad);
        $this->assertSame('CDMX', $dep->estado);
        $this->assertSame('Cuauhtémoc', $dep->municipio);
        $this->assertSame('PUE', $dep->metodo_pago);
        $this->assertSame('03', $dep->forma_pago);
    }

    public function test_registro_publico_con_plan_pago_otorga_un_mes_gratis(): void
    {
        $this->seed(LicenciaCatalogoSeeder::class);
        $licencia = Licencia::where('codigo', 'usuarios_10_mensual')->firstOrFail();

        $this->postJson('/api/v1/public/register-compania', [
            'companyName' => 'Acme Pago',
            'email' => 'pago@acme.com',
            'password' => 'password123',
            'firstName' => 'Ana',
            'lastNamePaternal' => 'Pérez',
            'licenciaId' => $licencia->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('requiere_pago', true)
            ->assertJsonPath('licencia.codigo', 'usuarios_10_mensual');

        $dep = Dependencia::where('nombre', 'Acme Pago')->firstOrFail();

        $this->assertSame('mensual', $dep->tipo_licencia);
        $this->assertSame(10, $dep->limite_usuarios);
        $this->assertNotNull($dep->trial_usado_at, 'El mes gratis se consume al registrarse');
        $this->assertTrue($dep->fecha_expiracion->between(now()->addDays(29), now()->addDays(31)));

        $this->assertDatabaseHas('suscripciones', [
            'dependencia_id' => $dep->id,
            'licencia_id' => $licencia->id,
            'plan' => 'usuarios_10_mensual',
            'estado' => 'trial',
        ]);
    }

    public function test_plan_gratuito_se_puede_activar_sin_pasarela_y_no_expira(): void
    {
        $dep = $this->dependencia(['trial_usado_at' => now()->subDays(2)]);
        $admin = $this->usuario($dep);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/licencias/1/comprar-mercadopago')
            ->assertOk()
            ->assertJsonPath('pasarela', 'none');

        $dep = $dep->fresh();
        $this->assertNull($dep->fecha_expiracion);
        $this->assertNotNull($dep->trial_usado_at);
    }

    public function test_dependencia_update_requiere_rol_administrativo(): void
    {
        $dep = $this->dependencia();
        $tecnico = $this->usuario($dep, 3, 'tecnico@licencias.com');

        $this->actingAs($tecnico, 'sanctum')
            ->putJson('/api/v1/dependencias/'.$dep->id, ['nombre' => 'Nombre cambiado'])
            ->assertStatus(403);

        $this->assertSame('Dep Licencias', $dep->fresh()->nombre);

        $admin = $this->usuario($dep, 1, 'admin2@licencias.com');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/dependencias/'.$dep->id, ['nombre' => 'Nombre cambiado'])
            ->assertOk();

        $this->assertSame('Nombre cambiado', $dep->fresh()->nombre);
    }

    public function test_no_se_pueden_usar_ejecutores_de_otra_dependencia(): void
    {
        $propia = $this->dependencia();
        $ajena = $this->dependencia(['nombre' => 'Dep Ajena']);

        $admin = $this->usuario($propia);
        $ajeno = $this->usuario($ajena, 3, 'tecnico@ajena.com');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/reportes', [
                'tipo' => 'libre',
                'fecha_inicio' => now()->subHour()->toDateTimeString(),
                'fecha_fin' => now()->toDateTimeString(),
                'ejecutores' => [$ajeno->id],
            ])
            ->assertStatus(422);

        $this->assertSame(0, \App\Models\Reporte::count());
    }

    public function test_evidencia_base64_svg_es_rechazada(): void
    {
        $dep = $this->dependencia();
        $admin = $this->usuario($dep);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/reportes', [
                'tipo' => 'libre',
                'fecha_inicio' => now()->subHour()->toDateTimeString(),
                'fecha_fin' => now()->toDateTimeString(),
                'evidenciaFotografica' => ['data:image/svg+xml;base64,'.base64_encode('<svg></svg>')],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['evidenciaFotografica']);
    }
}
