<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * El contrato dice qué gastos incluye y quién paga cada uno, y el formulario de
 * gastos los recibe para precompletar la carga.
 */
class GastosDelContratoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_contrato_guarda_sus_gastos(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('contratos.store'), [
            'property_id' => Property::factory()->create()->id,
            'tenant_id' => Tenant::factory()->create()->id,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2028-01-01',
            'monto_base' => '450000',
            'dia_vencimiento' => 10,
            'indice' => 'ipc',
            'frecuencia_meses' => 3,
            'redondeo' => 0,
            'estado' => 'activo',
            'gastos' => [
                ['categoria' => 'luz', 'descripcion' => ' Edenor ', 'a_cargo_de' => 'inquilino'],
                ['categoria' => 'abl', 'descripcion' => '', 'a_cargo_de' => 'mitades'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            ['categoria' => 'luz', 'descripcion' => 'Edenor', 'a_cargo_de' => 'inquilino'],
            ['categoria' => 'abl', 'descripcion' => null, 'a_cargo_de' => 'mitades'],
        ], Contract::query()->sole()->gastos);
    }

    public function test_rechaza_una_categoria_que_no_existe(): void
    {
        $admin = User::factory()->admin()->create();
        $contrato = Contract::factory()->create();

        $this->actingAs($admin)->put(route('contratos.update', $contrato), [
            'property_id' => $contrato->property_id,
            'tenant_id' => $contrato->tenant_id,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2028-01-01',
            'monto_base' => '450000',
            'monto_actual' => '450000',
            'dia_vencimiento' => 10,
            'indice' => 'ipc',
            'frecuencia_meses' => 3,
            'redondeo' => 0,
            'estado' => 'activo',
            'gastos' => [['categoria' => 'cualquiera', 'a_cargo_de' => 'inquilino']],
        ])->assertSessionHasErrors('gastos.0.categoria');
    }

    public function test_el_formulario_de_gastos_recibe_los_del_contrato(): void
    {
        $admin = User::factory()->admin()->create();
        $contrato = Contract::factory()->create([
            'gastos' => [
                ['categoria' => 'abl', 'descripcion' => null, 'a_cargo_de' => 'mitades'],
            ],
        ]);

        $this->actingAs($admin)->get(route('gastos.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contratos.0.id', $contrato->id)
                ->where('contratos.0.gastos.0.tipo', 'impuesto')
                ->where('contratos.0.gastos.0.concepto', 'ABL / Inmobiliario')
                ->where('contratos.0.gastos.0.a_cargo_de', 'mitades'));
    }
}
