<?php

namespace Tests\Feature;

use App\Enums\ACargoDe;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Repartos\RepartidorEntreDuenos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GastoCompartidoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /** @param  list<float|int>  $porcentajes */
    private function propiedadCon(array $porcentajes): Property
    {
        $property = Property::factory()->create();

        foreach ($porcentajes as $porcentaje) {
            $property->owners()->attach(Owner::factory()->create()->id, ['porcentaje' => $porcentaje]);
        }

        return $property;
    }

    /** @return numeric-string */
    private function sumaShares(Expense $gasto): string
    {
        return $gasto->shares()->get()->reduce(
            fn (string $acc, $share) => bcadd($acc, (string) $share->monto, 2),
            '0',
        );
    }

    public function test_parte_el_gasto_en_dos_mitades_exactas(): void
    {
        $gasto = Expense::factory()->compartido()->make(['monto' => '100.01']);

        // El centavo impar queda del lado del inquilino, no se pierde ni se duplica.
        $this->assertSame('50.00', $gasto->montoARepartir());
        $this->assertSame('50.01', $gasto->montoDelInquilino());
        $this->assertSame(
            $gasto->monto,
            bcadd($gasto->montoARepartir(), $gasto->montoDelInquilino(), 2),
        );
    }

    public function test_el_repartidor_solo_reparte_la_mitad_entre_los_duenos(): void
    {
        $property = $this->propiedadCon([60, 40]);
        $gasto = Expense::factory()->compartido()->create([
            'property_id' => $property->id,
            'monto' => '100000.00',
        ]);

        $partes = (new RepartidorEntreDuenos)->repartir($gasto);

        $this->assertEqualsCanonicalizing(
            ['30000.00', '20000.00'],
            $partes->pluck('monto')->map(fn ($m) => (string) $m)->all(),
        );
        $this->assertSame('50000.00', $this->sumaShares($gasto));
    }

    public function test_al_cargar_un_gasto_compartido_se_reparte_la_mitad(): void
    {
        $property = $this->propiedadCon([50, 50]);

        $this->actingAs($this->admin)
            ->post(route('gastos.store'), [
                'property_id' => $property->id,
                'tipo' => 'servicio',
                'categoria' => 'luz',
                'descripcion' => 'ABL',
                'periodo' => today()->toDateString(),
                'monto' => 80000,
                'vencimiento' => today()->addDays(10)->toDateString(),
                'a_cargo_de' => 'mitades',
                'pagado' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $gasto = Expense::query()->sole();
        $this->assertSame(ACargoDe::Mitades, $gasto->a_cargo_de);
        $this->assertSame('40000.00', $this->sumaShares($gasto));
        $this->assertEqualsCanonicalizing(
            ['20000.00', '20000.00'],
            $gasto->shares->map(fn ($s) => (string) $s->monto)->all(),
        );
    }

    public function test_pasar_de_propietarios_a_compartido_reajusta_el_reparto(): void
    {
        $property = $this->propiedadCon([100]);
        $gasto = Expense::factory()->extraordinario()->create([
            'property_id' => $property->id,
            'monto' => '100000.00',
        ]);
        (new RepartidorEntreDuenos)->repartir($gasto);
        $this->assertSame('100000.00', $this->sumaShares($gasto));

        $this->actingAs($this->admin)
            ->post(route('gastos.update', $gasto), [
                '_method' => 'PUT',
                'property_id' => $property->id,
                'tipo' => 'extraordinario',
                'categoria' => 'reparacion',
                'periodo' => $gasto->periodo->toDateString(),
                'monto' => 100000,
                'a_cargo_de' => 'mitades',
                'pagado' => false,
            ])
            ->assertRedirect();

        $this->assertSame('50000.00', $this->sumaShares($gasto->fresh()));
    }

    public function test_pasar_de_compartido_a_inquilino_borra_el_reparto(): void
    {
        $property = $this->propiedadCon([100]);
        $gasto = Expense::factory()->compartido()->create([
            'property_id' => $property->id,
            'monto' => '100000.00',
        ]);
        (new RepartidorEntreDuenos)->repartir($gasto);
        $this->assertSame(1, $gasto->shares()->count());

        $this->actingAs($this->admin)
            ->post(route('gastos.update', $gasto), [
                '_method' => 'PUT',
                'property_id' => $property->id,
                'tipo' => 'servicio',
                'categoria' => 'luz',
                'periodo' => $gasto->periodo->toDateString(),
                'monto' => 100000,
                'a_cargo_de' => 'inquilino',
                'pagado' => false,
            ])
            ->assertRedirect();

        $this->assertSame(0, $gasto->fresh()->shares()->count());
    }

    public function test_la_liquidacion_toma_la_mitad_del_gasto_compartido(): void
    {
        $property = $this->propiedadCon([100]);
        $owner = $property->owners()->sole();

        $gasto = Expense::factory()->compartido()->create([
            'property_id' => $property->id,
            'monto' => '90000.00',
            'periodo' => today()->startOfMonth(),
        ]);
        (new RepartidorEntreDuenos)->repartir($gasto);

        $this->actingAs($this->admin)
            ->get(route('liquidaciones.index', ['periodo' => today()->format('Y-m')]))
            ->assertInertia(fn ($page) => $page
                ->where('propietarios.0.id', $owner->id)
                ->where('propietarios.0.gastos_total', '45000.00')
                ->where('propietarios.0.gastos.0.monto', '45000.00')
                ->where('propietarios.0.gastos.0.compartido', true));
    }

    public function test_el_cuadro_del_inquilino_incluye_la_mitad_del_gasto_compartido(): void
    {
        $property = $this->propiedadCon([100]);
        $tenant = Tenant::factory()->create();
        Contract::factory()->create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
        ]);

        Expense::factory()->compartido()->create([
            'property_id' => $property->id,
            'descripcion' => 'Service de la caldera',
            'monto' => '60000.00',
            'vencimiento' => today()->startOfMonth()->addDays(12),
        ]);

        $this->actingAs($this->admin)
            ->get(route('propiedades.show', $property))
            ->assertInertia(fn ($page) => $page
                ->has('mensajeInquilino.gastos', 1)
                ->where('mensajeInquilino.gastos.0.concepto', 'Service de la caldera (mitad)')
                ->where('mensajeInquilino.gastos.0.monto', '30000.00'));
    }

    public function test_la_opcion_los_dos_aparece_en_el_formulario(): void
    {
        $this->actingAs($this->admin)
            ->get(route('gastos.create'))
            ->assertInertia(fn ($page) => $page
                ->where('aCargoDe', fn ($opciones) => collect($opciones)->pluck('value')->all() === [
                    'inquilino', 'propietarios', 'mitades',
                ]));
    }
}
