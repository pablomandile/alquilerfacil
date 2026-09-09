<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El alquiler se lleva siempre en pesos enteros: los centavos que venga a tener
 * un importe cargado a mano se redondean al guardarlo.
 */
class ImporteDelAlquilerEnPesosEnterosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, mixed> */
    private function datosValidos(array $extra = []): array
    {
        return [
            'property_id' => Property::factory()->create()->id,
            'tenant_id' => Tenant::factory()->create()->id,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2028-01-01',
            'monto_base' => '450000',
            'dia_vencimiento' => 10,
            'deposito' => null,
            'indice' => 'ipc',
            'frecuencia_meses' => 3,
            'proximo_ajuste' => null,
            'redondeo' => 0,
            'estado' => 'activo',
            'notas' => null,
            ...$extra,
        ];
    }

    public function test_al_crear_el_contrato_el_alquiler_queda_sin_centavos(): void
    {
        $this->actingAs($this->admin)
            ->post(route('contratos.store'), $this->datosValidos(['monto_base' => '478248.16']))
            ->assertRedirect();

        $contrato = Contract::query()->latest('id')->firstOrFail();

        $this->assertSame('478248.00', (string) $contrato->monto_base);
        $this->assertSame('478248.00', (string) $contrato->monto_actual);
    }

    public function test_al_editar_el_alquiler_vigente_queda_sin_centavos(): void
    {
        $contrato = Contract::factory()->create(['monto_actual' => 450000]);

        $this->actingAs($this->admin)
            ->put(route('contratos.update', $contrato), $this->datosValidos([
                'property_id' => $contrato->property_id,
                'tenant_id' => $contrato->tenant_id,
                'monto_base' => '450000',
                'monto_actual' => '512900.55',
            ]))
            ->assertRedirect();

        $this->assertSame('512901.00', (string) $contrato->fresh()->monto_actual);
    }
}
