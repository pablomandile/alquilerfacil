<?php

namespace Tests\Feature;

use App\Enums\ACargoDe;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_acumula_el_alquiler_por_propiedad_y_el_neto(): void
    {
        $admin = User::factory()->admin()->create();

        // Rivadavia: facturado 200.000, cobrado entero, 30.000 de extraordinario
        // a cargo de los dueños -> neto 170.000.
        $rivadavia = Property::factory()->create(['alias' => 'Rivadavia']);
        $c1 = Contract::factory()->create(['property_id' => $rivadavia->id]);
        $cargo1 = RentCharge::factory()->conMonto(200000)->create(['contract_id' => $c1->id]);
        Payment::factory()->de(200000)->create(['rent_charge_id' => $cargo1->id]);
        Expense::factory()->extraordinario()->create([
            'property_id' => $rivadavia->id, 'monto' => 30000, 'a_cargo_de' => ACargoDe::Propietarios,
        ]);

        // Belgrano: facturado 100.000, cobrado 50.000 -> neto 50.000.
        $belgrano = Property::factory()->create(['alias' => 'Belgrano']);
        $c2 = Contract::factory()->create(['property_id' => $belgrano->id]);
        $cargo2 = RentCharge::factory()->conMonto(100000)->create(['contract_id' => $c2->id]);
        Payment::factory()->de(50000)->create(['rent_charge_id' => $cargo2->id]);

        // Sin movimiento: no aparece en la tarjeta.
        Property::factory()->create(['alias' => 'Cochera']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('alquileres.por_propiedad', 2)
                // Ordenadas por neto descendente.
                ->where('alquileres.por_propiedad.0.alias', 'Rivadavia')
                ->where('alquileres.por_propiedad.0.cobrado', '200000.00')
                ->where('alquileres.por_propiedad.0.neto', '170000.00')
                ->where('alquileres.por_propiedad.1.alias', 'Belgrano')
                ->where('alquileres.por_propiedad.1.neto', '50000.00')
                ->where('alquileres.facturado', '300000.00')
                ->where('alquileres.cobrado', '250000.00')
                ->where('alquileres.gastos_extraordinarios', '30000.00')
                ->where('alquileres.neto', '220000.00')
            );
    }

    public function test_un_propietario_solo_acumula_sus_propiedades(): void
    {
        $owner = Owner::factory()->conAcceso()->create();

        $suya = Property::factory()->create();
        $suya->owners()->attach($owner->id, ['porcentaje' => 100]);
        $c = Contract::factory()->create(['property_id' => $suya->id]);
        $cargo = RentCharge::factory()->conMonto(120000)->create(['contract_id' => $c->id]);
        Payment::factory()->de(120000)->create(['rent_charge_id' => $cargo->id]);

        $ajena = Property::factory()->create();
        $ca = Contract::factory()->create(['property_id' => $ajena->id]);
        RentCharge::factory()->conMonto(999999)->create(['contract_id' => $ca->id]);

        $this->actingAs($owner->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('alquileres.por_propiedad', 1)
                ->where('alquileres.cobrado', '120000.00')
            );
    }
}
