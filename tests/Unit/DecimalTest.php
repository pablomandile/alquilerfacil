<?php

namespace Tests\Unit;

use App\Support\Decimal;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function test_redondear_redondea_media_unidad_hacia_afuera(): void
    {
        $this->assertSame('1234.57', Decimal::redondear('1234.567'));
        $this->assertSame('1234.56', Decimal::redondear('1234.564'));
        $this->assertSame('1234.57', Decimal::redondear('1234.565'));
        $this->assertSame('2.01', Decimal::redondear('2.005'));
        $this->assertSame('-2.01', Decimal::redondear('-2.005'));
    }

    public function test_redondear_a_cero_decimales(): void
    {
        $this->assertSame('426', Decimal::redondear('425.5', 0));
        $this->assertSame('425', Decimal::redondear('425.49', 0));
        $this->assertSame('425', Decimal::redondear('425.4', 0));
    }

    public function test_a_pesos_enteros_no_deja_centavos(): void
    {
        $this->assertSame('478248.00', Decimal::aPesosEnteros('478248.16'));
        $this->assertSame('478249.00', Decimal::aPesosEnteros('478248.60'));
        $this->assertSame('512901.00', Decimal::aPesosEnteros('512900.55'));
        $this->assertSame('400000.00', Decimal::aPesosEnteros('400000'));
    }

    public function test_a_multiplo_de_redondea_al_centenar_o_millar(): void
    {
        $this->assertSame('425000.00', Decimal::aMultiploDe('425480.00', 1000));
        $this->assertSame('426000.00', Decimal::aMultiploDe('425500.00', 1000));
        $this->assertSame('477900.00', Decimal::aMultiploDe('477913.44', 100));
        // Sin múltiplo real, igual quedan pesos enteros.
        $this->assertSame('477913.00', Decimal::aMultiploDe('477913.44', 0));
    }
}
