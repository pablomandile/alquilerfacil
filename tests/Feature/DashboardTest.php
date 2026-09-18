<?php

namespace Tests\Feature;

use App\Enums\ACargoDe;
use App\Enums\CategoriaGasto;
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

        // Belgrano: facturado 100.000, cobrado 50.000, 15.000 de gastos
        // ordinarios a cargo de los dueños -> neto 35.000.
        $belgrano = Property::factory()->create(['alias' => 'Belgrano']);
        $c2 = Contract::factory()->create(['property_id' => $belgrano->id]);
        $cargo2 = RentCharge::factory()->conMonto(100000)->create(['contract_id' => $c2->id]);
        Payment::factory()->de(50000)->create(['rent_charge_id' => $cargo2->id]);
        Expense::factory()->create([
            'property_id' => $belgrano->id, 'monto' => 15000, 'a_cargo_de' => ACargoDe::Propietarios,
        ]);

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
                ->where('alquileres.por_propiedad.1.neto', '35000.00')
                ->where('alquileres.facturado', '300000.00')
                ->where('alquileres.cobrado', '250000.00')
                ->where('alquileres.gastos_ordinarios', '15000.00')
                ->where('alquileres.gastos_extraordinarios', '30000.00')
                ->where('alquileres.neto', '205000.00')
            );
    }

    public function test_abre_los_gastos_de_cada_propiedad_por_categoria(): void
    {
        $admin = User::factory()->admin()->create();

        // Rivadavia: dos gastos de luz (se suman en una porción) y uno de agua.
        $rivadavia = Property::factory()->create(['alias' => 'Rivadavia']);
        Expense::factory()->create(['property_id' => $rivadavia->id, 'categoria' => CategoriaGasto::Luz, 'monto' => 10000]);
        Expense::factory()->create(['property_id' => $rivadavia->id, 'categoria' => CategoriaGasto::Luz, 'monto' => 5000.50]);
        Expense::factory()->create(['property_id' => $rivadavia->id, 'categoria' => CategoriaGasto::Agua, 'monto' => 3000]);

        $belgrano = Property::factory()->create(['alias' => 'Belgrano']);
        Expense::factory()->create(['property_id' => $belgrano->id, 'categoria' => CategoriaGasto::Expensas, 'monto' => 40000]);

        // Sin gastos: no aparece en el gráfico.
        Property::factory()->create(['alias' => 'Cochera']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Las propiedades van de mayor a menor gasto.
                ->has('gastos.por_propiedad', 2)
                ->where('gastos.por_propiedad.0.alias', 'Belgrano')
                ->where('gastos.por_propiedad.0.total', '40000.00')
                ->has('gastos.por_propiedad.0.categorias', 1)
                ->where('gastos.por_propiedad.1.alias', 'Rivadavia')
                ->where('gastos.por_propiedad.1.total', '18000.50')
                // Las categorías, en el orden del enum, con la luz sumada.
                ->has('gastos.por_propiedad.1.categorias', 2)
                ->where('gastos.por_propiedad.1.categorias.0.clave', 'luz')
                ->where('gastos.por_propiedad.1.categorias.0.monto', '15000.50')
                ->where('gastos.por_propiedad.1.categorias.1.clave', 'agua')
                ->where('gastos.por_propiedad.1.categorias.1.monto', '3000.00')
                ->where('gastos.total', '58000.50')
            );
    }

    public function test_el_color_de_una_categoria_no_depende_de_cuanto_suma(): void
    {
        $admin = User::factory()->admin()->create();

        // La luz es el primer color del enum y las expensas el cuarto, aunque
        // acá las expensas sean el gasto más grande.
        $propiedad = Property::factory()->create();
        Expense::factory()->create(['property_id' => $propiedad->id, 'categoria' => CategoriaGasto::Luz, 'monto' => 1000]);
        Expense::factory()->create(['property_id' => $propiedad->id, 'categoria' => CategoriaGasto::Expensas, 'monto' => 90000]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('gastos.por_propiedad.0.categorias.0.color', 1)
                ->where('gastos.por_propiedad.0.categorias.1.color', 4)
                // La leyenda nombra sólo lo que aparece, con el mismo color.
                ->has('gastos.leyenda', 2)
                ->where('gastos.leyenda.0.clave', 'luz')
                ->where('gastos.leyenda.0.color', 1)
                ->where('gastos.leyenda.1.clave', 'expensas')
                ->where('gastos.leyenda.1.color', 4)
            );
    }

    public function test_arma_la_evolucion_mensual_de_alquileres_y_gastos(): void
    {
        $admin = User::factory()->admin()->create();
        $propiedad = Property::factory()->create();
        $contrato = Contract::factory()->create(['property_id' => $propiedad->id]);

        // Dos meses con alquiler, uno solo con gastos.
        RentCharge::factory()->conMonto(100000)->delPeriodo(today()->subMonth())->create(['contract_id' => $contrato->id]);
        RentCharge::factory()->conMonto(120000)->delPeriodo(today())->create(['contract_id' => $contrato->id]);
        Expense::factory()->create([
            'property_id' => $propiedad->id,
            'periodo' => today()->startOfMonth(),
            'monto' => 15000,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Arranca en el primer mes con movimiento, no doce meses atrás.
                ->has('evolucion', 2)
                ->where('evolucion.0.clave', today()->subMonth()->format('Y-m'))
                ->where('evolucion.0.alquileres', '100000.00')
                // El mes sin gastos va en null, que corta la línea; no en cero.
                ->where('evolucion.0.gastos', null)
                ->where('evolucion.1.clave', today()->format('Y-m'))
                ->where('evolucion.1.alquileres', '120000.00')
                ->where('evolucion.1.gastos', '15000.00')
            );
    }

    public function test_la_evolucion_mensual_solo_mira_lo_que_el_usuario_ve(): void
    {
        $owner = Owner::factory()->conAcceso()->create();

        $suya = Property::factory()->create();
        $suya->owners()->attach($owner->id, ['porcentaje' => 100]);
        $contrato = Contract::factory()->create(['property_id' => $suya->id]);
        RentCharge::factory()->conMonto(80000)->delPeriodo(today())->create(['contract_id' => $contrato->id]);

        $ajena = Property::factory()->create();
        $otroContrato = Contract::factory()->create(['property_id' => $ajena->id]);
        RentCharge::factory()->conMonto(999999)->delPeriodo(today())->create(['contract_id' => $otroContrato->id]);
        Expense::factory()->create(['property_id' => $ajena->id, 'periodo' => today()->startOfMonth(), 'monto' => 5000]);

        $this->actingAs($owner->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('evolucion', 1)
                ->where('evolucion.0.alquileres', '80000.00')
                ->where('evolucion.0.gastos', null)
            );
    }

    public function test_un_propietario_solo_ve_los_gastos_de_sus_propiedades(): void
    {
        $owner = Owner::factory()->conAcceso()->create();

        $suya = Property::factory()->create(['alias' => 'Suya']);
        $suya->owners()->attach($owner->id, ['porcentaje' => 100]);
        Expense::factory()->create(['property_id' => $suya->id, 'monto' => 7000]);

        $ajena = Property::factory()->create();
        Expense::factory()->create(['property_id' => $ajena->id, 'monto' => 99999]);

        $this->actingAs($owner->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('gastos.por_propiedad', 1)
                ->where('gastos.por_propiedad.0.alias', 'Suya')
                ->where('gastos.total', '7000.00')
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
