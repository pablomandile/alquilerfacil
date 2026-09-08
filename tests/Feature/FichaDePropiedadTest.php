<?php

namespace Tests\Feature;

use App\Enums\ACargoDe;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class FichaDePropiedadTest extends TestCase
{
    use RefreshDatabase;

    public function test_totaliza_lo_facturado_y_lo_cobrado_del_contrato_vigente_y_los_anteriores(): void
    {
        $admin = User::factory()->admin()->create();
        $propiedad = Property::factory()->create();

        // Contrato anterior: dos cargos de 100.000, cobrado 160.000.
        $anterior = Contract::factory()->finalizado()->desde(Date::parse('2024-01-01'))
            ->create(['property_id' => $propiedad->id]);
        $c1 = RentCharge::factory()->conMonto(100000)->create(['contract_id' => $anterior->id, 'periodo' => '2024-01-01']);
        $c2 = RentCharge::factory()->conMonto(100000)->create(['contract_id' => $anterior->id, 'periodo' => '2024-02-01']);
        Payment::factory()->de(100000)->create(['rent_charge_id' => $c1->id]);
        Payment::factory()->de(60000)->create(['rent_charge_id' => $c2->id]);

        // Contrato vigente: un cargo de 200.000, cobrado entero.
        $actual = Contract::factory()->desde(Date::parse('2025-06-01'))
            ->create(['property_id' => $propiedad->id]);
        $c3 = RentCharge::factory()->conMonto(200000)->create(['contract_id' => $actual->id, 'periodo' => '2025-06-01']);
        Payment::factory()->de(200000)->create(['rent_charge_id' => $c3->id]);

        // Sólo el extraordinario a cargo de los propietarios resta del neto.
        Expense::factory()->extraordinario()->create(['property_id' => $propiedad->id, 'monto' => 30000]);
        Expense::factory()->extraordinario()->create([
            'property_id' => $propiedad->id, 'monto' => 50000, 'a_cargo_de' => ACargoDe::Inquilino,
        ]);
        Expense::factory()->create(['property_id' => $propiedad->id, 'monto' => 12345]);

        $this->actingAs($admin)
            ->get(route('propiedades.show', $propiedad))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('propiedad.totales.facturado', '400000.00')
                ->where('propiedad.totales.cobrado', '360000.00')
                ->where('propiedad.totales.gastos_extraordinarios', '30000.00')
                ->where('propiedad.totales.neto', '330000.00')
                ->has('propiedad.totales.por_contrato', 2)
                // Ordenados por fecha de inicio: primero el vigente.
                ->where('propiedad.totales.por_contrato.0.id', $actual->id)
                ->where('propiedad.totales.por_contrato.0.facturado', '200000.00')
                ->where('propiedad.totales.por_contrato.0.cobrado', '200000.00')
                ->where('propiedad.totales.por_contrato.1.id', $anterior->id)
                ->where('propiedad.totales.por_contrato.1.facturado', '200000.00')
                ->where('propiedad.totales.por_contrato.1.cobrado', '160000.00')
            );
    }

    public function test_una_propiedad_sin_contratos_totaliza_en_cero(): void
    {
        $admin = User::factory()->admin()->create();
        $propiedad = Property::factory()->create();

        $this->actingAs($admin)
            ->get(route('propiedades.show', $propiedad))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('propiedad.totales.facturado', '0.00')
                ->where('propiedad.totales.cobrado', '0.00')
                ->where('propiedad.totales.neto', '0.00')
                ->has('propiedad.totales.por_contrato', 0)
            );
    }
}
