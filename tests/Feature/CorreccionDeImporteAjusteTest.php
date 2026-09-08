<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Owner;
use App\Models\Property;
use App\Models\RentAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorreccionDeImporteAjusteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $duenio;

    private Property $suya;

    private Property $ajena;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $owner = Owner::factory()->conAcceso()->create();
        $this->duenio = $owner->user;

        $this->suya = Property::factory()->create();
        $this->suya->owners()->attach($owner->id, ['porcentaje' => 100]);

        $this->ajena = Property::factory()->create();
        $this->ajena->owners()->attach(Owner::factory()->create()->id, ['porcentaje' => 100]);
    }

    /** @param  array<string, mixed>  $attrs */
    private function ajusteAplicado(Property $propiedad, array $attrs = []): RentAdjustment
    {
        $contrato = Contract::factory()->create([
            'property_id' => $propiedad->id,
            'monto_actual' => 478248.16,
        ]);

        return RentAdjustment::factory()->aplicado()->create([
            'contract_id' => $contrato->id,
            'monto_anterior' => 450000,
            'monto_nuevo' => 478248.16,
            ...$attrs,
        ]);
    }

    public function test_corrige_el_importe_del_ultimo_ajuste_aplicado(): void
    {
        $ajuste = $this->ajusteAplicado($this->suya);

        $this->actingAs($this->duenio)
            ->patch(route('ajustes.actualizar', $ajuste), ['monto' => '478000'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('478000.00', (string) $ajuste->fresh()->monto_nuevo);
        $this->assertSame('478000.00', (string) $ajuste->contract->fresh()->monto_actual);
        $this->assertStringContainsString('corregido a mano', (string) $ajuste->fresh()->notas);
    }

    public function test_no_toca_el_indice_ni_la_fecha_del_proximo_ajuste(): void
    {
        $ajuste = $this->ajusteAplicado($this->suya);
        $proximo = $ajuste->contract->proximo_ajuste?->toDateString();
        $variacion = (string) $ajuste->variacion_porcentual;

        $this->actingAs($this->admin)
            ->patch(route('ajustes.actualizar', $ajuste), ['monto' => '478000'])
            ->assertRedirect();

        $ajuste->refresh();
        $this->assertSame($variacion, (string) $ajuste->variacion_porcentual);
        $this->assertSame('10600.00000000', (string) $ajuste->valor_indice_hasta);
        $this->assertSame($proximo, $ajuste->contract->fresh()->proximo_ajuste?->toDateString());
    }

    public function test_no_corrige_un_ajuste_todavia_propuesto(): void
    {
        $contrato = Contract::factory()->create([
            'property_id' => $this->suya->id,
            'monto_actual' => 450000,
        ]);
        $ajuste = RentAdjustment::factory()->create([
            'contract_id' => $contrato->id,
            'monto_nuevo' => 478248.16,
        ]);

        $this->actingAs($this->duenio)
            ->patch(route('ajustes.actualizar', $ajuste), ['monto' => '478000'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('478248.16', (string) $ajuste->fresh()->monto_nuevo);
        $this->assertSame('450000.00', (string) $contrato->fresh()->monto_actual);
    }

    public function test_no_corrige_un_ajuste_que_no_es_el_ultimo(): void
    {
        $contrato = Contract::factory()->create([
            'property_id' => $this->suya->id,
            'monto_actual' => 520000,
        ]);

        $viejo = RentAdjustment::factory()->aplicado()->create([
            'contract_id' => $contrato->id,
            'vigencia_desde' => '2026-05-01',
            'monto_nuevo' => 478000,
        ]);
        RentAdjustment::factory()->aplicado()->create([
            'contract_id' => $contrato->id,
            'vigencia_desde' => '2026-08-01',
            'monto_nuevo' => 520000,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('ajustes.actualizar', $viejo), ['monto' => '470000'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('478000.00', (string) $viejo->fresh()->monto_nuevo);
        $this->assertSame('520000.00', (string) $contrato->fresh()->monto_actual);
    }

    public function test_un_copropietario_no_corrige_el_ajuste_de_una_propiedad_ajena(): void
    {
        $ajuste = $this->ajusteAplicado($this->ajena);

        $this->actingAs($this->duenio)
            ->patch(route('ajustes.actualizar', $ajuste), ['monto' => '1'])
            ->assertForbidden();

        $this->assertSame('478248.16', (string) $ajuste->fresh()->monto_nuevo);
    }

    public function test_el_importe_tiene_que_ser_un_numero_positivo(): void
    {
        $ajuste = $this->ajusteAplicado($this->suya);

        $this->actingAs($this->admin)
            ->patch(route('ajustes.actualizar', $ajuste), ['monto' => '-5'])
            ->assertSessionHasErrors('monto');

        $this->actingAs($this->admin)
            ->patch(route('ajustes.actualizar', $ajuste), ['monto' => 'ni un número'])
            ->assertSessionHasErrors('monto');

        $this->assertSame('478248.16', (string) $ajuste->fresh()->monto_nuevo);
    }

    public function test_la_pantalla_marca_editable_solo_el_ultimo_aplicado(): void
    {
        $contrato = Contract::factory()->create(['property_id' => $this->suya->id]);

        $viejo = RentAdjustment::factory()->aplicado()->create([
            'contract_id' => $contrato->id,
            'vigencia_desde' => '2026-05-01',
        ]);
        $ultimo = RentAdjustment::factory()->aplicado()->create([
            'contract_id' => $contrato->id,
            'vigencia_desde' => '2026-08-01',
        ]);
        RentAdjustment::factory()->create([
            'contract_id' => $contrato->id,
            'vigencia_desde' => '2026-11-01',
        ]);

        $this->actingAs($this->admin)
            ->get(route('ajustes.index'))
            ->assertInertia(fn ($page) => $page
                ->where('historial', function ($historial) use ($ultimo, $viejo) {
                    $porId = collect($historial)->keyBy('id');

                    return $porId[$ultimo->id]['editable'] === true
                        && $porId[$viejo->id]['editable'] === false;
                })
                ->where('propuestos', fn ($propuestos) => collect($propuestos)
                    ->every(fn ($a) => $a['editable'] === false)));
    }
}
