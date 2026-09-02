<?php

namespace Tests\Feature;

use App\Exceptions\RepartoInvalidoException;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Property;
use App\Services\Repartos\RepartidorEntreDuenos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepartoEntreDuenosTest extends TestCase
{
    use RefreshDatabase;

    private RepartidorEntreDuenos $repartidor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repartidor = new RepartidorEntreDuenos;
    }

    /**
     * Crea una propiedad con dueños en los porcentajes dados y un gasto por el
     * monto indicado, listo para repartir.
     *
     * @param  list<float|int|string>  $porcentajes
     */
    private function gastoDe(string $monto, array $porcentajes): Expense
    {
        $property = Property::factory()->create();

        foreach ($porcentajes as $porcentaje) {
            $property->owners()->attach(
                Owner::factory()->create()->id,
                ['porcentaje' => $porcentaje]
            );
        }

        return Expense::factory()->extraordinario()->create([
            'property_id' => $property->id,
            'monto' => $monto,
        ]);
    }

    public function test_reparte_segun_el_porcentaje_de_cada_dueno(): void
    {
        $gasto = $this->gastoDe('100000.00', [60, 40]);

        $partes = $this->repartidor->repartir($gasto);

        $this->assertEqualsCanonicalizing(
            ['60000.00', '40000.00'],
            $partes->pluck('monto')->map(fn ($m) => (string) $m)->all()
        );
    }

    /**
     * El caso que rompe la implementación ingenua: redondear cada parte por
     * separado pierde un centavo. Las partes tienen que sumar el total exacto.
     */
    public function test_las_partes_suman_exactamente_el_total_cuando_no_divide_redondo(): void
    {
        $casos = [
            ['333.33', [50, 50]],
            ['1000000.01', [33.33, 33.33, 33.34]],
            ['450000.00', [33.33, 33.33, 33.34]],
            ['87654.321', [25, 25, 25, 25]],
            ['0.05', [33.33, 33.33, 33.34]],
            ['999999.99', [70, 20, 10]],
        ];

        foreach ($casos as [$monto, $porcentajes]) {
            $gasto = $this->gastoDe($monto, $porcentajes);

            $suma = $this->repartidor->repartir($gasto)
                ->reduce(fn (string $acc, $parte) => bcadd($acc, (string) $parte->monto, 2), '0');

            $this->assertSame(
                bcadd($gasto->monto, '0', 2),
                $suma,
                "El reparto de {$monto} entre ".implode('/', $porcentajes).'% no suma el total.'
            );
        }
    }

    public function test_el_residuo_va_al_dueno_de_mayor_porcentaje(): void
    {
        $gasto = $this->gastoDe('1000000.01', [33.33, 33.34, 33.33]);

        $partes = $this->repartidor->repartir($gasto)->sortByDesc('monto')->values();

        // 33,34% de 1.000.000,01 trunca a 333.400,00 y se lleva el centavo sobrante.
        $this->assertSame('33.34', (string) $partes[0]->porcentaje);
        $this->assertSame('333400.01', (string) $partes[0]->monto);
    }

    public function test_a_igual_porcentaje_el_residuo_va_siempre_al_mismo_dueno(): void
    {
        $gasto = $this->gastoDe('333.33', [50, 50]);

        $primera = $this->repartidor->repartir($gasto)
            ->sortByDesc('monto')->first()->owner_id;

        // Repartir de nuevo sobre los mismos datos tiene que dar lo mismo.
        $segunda = $this->repartidor->repartir($gasto->fresh())
            ->sortByDesc('monto')->first()->owner_id;

        $this->assertSame($primera, $segunda);
    }

    public function test_repartir_dos_veces_reemplaza_el_reparto_anterior(): void
    {
        $gasto = $this->gastoDe('100000.00', [60, 40]);

        $this->repartidor->repartir($gasto);
        $this->repartidor->repartir($gasto->fresh());

        $this->assertSame(2, $gasto->shares()->count());
    }

    public function test_falla_si_los_porcentajes_no_suman_100(): void
    {
        $gasto = $this->gastoDe('100000.00', [60, 30]);

        $this->expectException(RepartoInvalidoException::class);
        $this->expectExceptionMessage('suman 90.00% en vez de 100%');

        $this->repartidor->repartir($gasto);
    }

    public function test_falla_si_la_propiedad_no_tiene_propietarios(): void
    {
        $gasto = $this->gastoDe('100000.00', []);

        $this->expectException(RepartoInvalidoException::class);
        $this->expectExceptionMessage('no tiene propietarios cargados');

        $this->repartidor->repartir($gasto);
    }
}
