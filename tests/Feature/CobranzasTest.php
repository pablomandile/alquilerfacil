<?php

namespace Tests\Feature;

use App\Enums\EstadoCargo;
use App\Models\Contract;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentCharge;
use App\Services\Cobranzas\GeneradorDeCargos;
use App\Services\Repartos\RepartidorEntreDuenos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class CobranzasTest extends TestCase
{
    use RefreshDatabase;

    private GeneradorDeCargos $generador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generador = new GeneradorDeCargos(new RepartidorEntreDuenos);
    }

    /** @param  list<int|float>  $porcentajes */
    private function contratoCon(array $porcentajes, float|int $monto = 500000, int $dia = 10): Contract
    {
        $property = Property::factory()->create();

        foreach ($porcentajes as $porcentaje) {
            $property->owners()->attach(
                Owner::factory()->create()->id,
                ['porcentaje' => $porcentaje]
            );
        }

        return Contract::factory()->create([
            'property_id' => $property->id,
            'fecha_inicio' => Date::parse('2026-01-01'),
            'fecha_fin' => Date::parse('2027-12-31'),
            'monto_base' => $monto,
            'monto_actual' => $monto,
            'dia_vencimiento' => $dia,
        ]);
    }

    public function test_emite_el_cargo_del_mes_con_el_monto_vigente(): void
    {
        $contrato = $this->contratoCon([100], 500000);

        $this->generador->generar(Date::parse('2026-09-01'));

        $cargo = $contrato->charges()->first();

        $this->assertSame('500000.00', (string) $cargo->monto);
        $this->assertSame('2026-09-01', $cargo->periodo->toDateString());
        $this->assertSame('2026-09-10', $cargo->vencimiento->toDateString());
    }

    public function test_reparte_el_alquiler_entre_los_duenos_al_emitir(): void
    {
        $contrato = $this->contratoCon([60, 40], 500000);

        $this->generador->generar(Date::parse('2026-09-01'));

        $partes = $contrato->charges()->first()->shares;

        $this->assertCount(2, $partes);
        $this->assertEqualsCanonicalizing(
            ['300000.00', '200000.00'],
            $partes->pluck('monto')->map(fn ($m) => (string) $m)->all()
        );
    }

    /** Que sea idempotente es lo que permite volver a correrlo si falló el cron. */
    public function test_correrlo_dos_veces_no_duplica_cargos(): void
    {
        $contrato = $this->contratoCon([100]);

        $this->generador->generar(Date::parse('2026-09-01'));
        $segunda = $this->generador->generar(Date::parse('2026-09-01'));

        $this->assertSame(1, $contrato->charges()->count());
        $this->assertFalse($segunda->first()->nuevo);
    }

    public function test_recorta_el_vencimiento_al_ultimo_dia_del_mes(): void
    {
        $contrato = $this->contratoCon([100], dia: 31);

        // Noviembre tiene 30 días: no puede vencer el 31.
        $this->generador->generar(Date::parse('2026-11-01'));

        $this->assertSame('2026-11-30', $contrato->charges()->first()->vencimiento->toDateString());
    }

    public function test_no_emite_cargos_para_contratos_terminados(): void
    {
        $contrato = $this->contratoCon([100]);
        $contrato->update(['fecha_fin' => Date::parse('2026-06-30')]);

        $this->generador->generar(Date::parse('2026-09-01'));

        $this->assertSame(0, $contrato->charges()->count());
    }

    public function test_no_emite_el_cargo_si_los_porcentajes_estan_mal_cargados(): void
    {
        // Sin saber a quién le corresponde cada peso, emitir el cargo sería peor
        // que no emitirlo: quedaría plata sin asignar y nadie se enteraría.
        $contrato = $this->contratoCon([60, 30]);

        $resultado = $this->generador->generar(Date::parse('2026-09-01'))->first();

        $this->assertFalse($resultado->exitoso());
        $this->assertSame(0, $contrato->charges()->count());
        $this->assertStringContainsString('suman 90.00%', $resultado->error);
    }

    public function test_los_pagos_parciales_llevan_el_cargo_de_pendiente_a_pagado(): void
    {
        $cargo = RentCharge::factory()->conMonto(500000)->create([
            'vencimiento' => today()->addDays(5),
        ]);

        $this->assertSame(EstadoCargo::Pendiente, $cargo->estado);

        Payment::factory()->de(200000)->create(['rent_charge_id' => $cargo->id]);
        $this->assertSame(EstadoCargo::Parcial, $cargo->fresh()->estado);
        $this->assertSame('300000.00', $cargo->fresh()->saldo());

        Payment::factory()->de(300000)->create(['rent_charge_id' => $cargo->id]);
        $this->assertSame(EstadoCargo::Pagado, $cargo->fresh()->estado);
        $this->assertSame('0.00', $cargo->fresh()->saldo());
    }

    public function test_borrar_un_pago_vuelve_atras_el_estado_del_cargo(): void
    {
        $cargo = RentCharge::factory()->conMonto(500000)->create([
            'vencimiento' => today()->addDays(5),
        ]);

        $pago = Payment::factory()->de(500000)->create(['rent_charge_id' => $cargo->id]);
        $this->assertSame(EstadoCargo::Pagado, $cargo->fresh()->estado);

        $pago->delete();
        $this->assertSame(EstadoCargo::Pendiente, $cargo->fresh()->estado);
    }

    public function test_un_cargo_impago_y_vencido_queda_marcado_como_vencido(): void
    {
        $cargo = RentCharge::factory()->conMonto(500000)->create([
            'vencimiento' => today()->subDays(5),
        ]);

        $cargo->actualizarEstado();

        $this->assertSame(EstadoCargo::Vencido, $cargo->fresh()->estado);
    }

    /**
     * Con un pago parcial el estado útil es «parcial», aunque esté vencido: ya
     * deja ver que falta plata y no borra la información de que algo entró.
     */
    public function test_un_pago_parcial_gana_sobre_el_vencimiento(): void
    {
        $cargo = RentCharge::factory()->conMonto(500000)->create([
            'vencimiento' => today()->subDays(5),
        ]);

        Payment::factory()->de(100000)->create(['rent_charge_id' => $cargo->id]);

        $this->assertSame(EstadoCargo::Parcial, $cargo->fresh()->estado);
    }
}
