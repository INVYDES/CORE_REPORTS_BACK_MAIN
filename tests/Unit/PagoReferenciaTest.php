<?php

namespace Tests\Unit;

use App\Support\PagoReferencia;
use PHPUnit\Framework\TestCase;

class PagoReferenciaTest extends TestCase
{
    public function test_parsea_una_referencia_valida(): void
    {
        $this->assertSame(
            ['licencia' => 2, 'dependencia' => 15],
            PagoReferencia::parsear('CS-2-15')
        );
    }

    public function test_rechaza_referencias_con_formato_incorrecto(): void
    {
        $this->assertNull(PagoReferencia::parsear(null));
        $this->assertNull(PagoReferencia::parsear(''));
        $this->assertNull(PagoReferencia::parsear('MP-2-15'));
        $this->assertNull(PagoReferencia::parsear('CS-2'));
        $this->assertNull(PagoReferencia::parsear('CS-2-15-extra'));
        $this->assertNull(PagoReferencia::parsear('CS-abc-15'));
    }

    public function test_solo_pertenece_a_la_dependencia_de_la_referencia(): void
    {
        $this->assertTrue(PagoReferencia::perteneceA('CS-3-42', 42));
        $this->assertFalse(PagoReferencia::perteneceA('CS-3-42', 43));
        $this->assertFalse(PagoReferencia::perteneceA(null, 43));
        $this->assertFalse(PagoReferencia::perteneceA('desconocida', 43));
    }
}
