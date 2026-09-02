<?php

namespace Database\Factories;

use App\Enums\EstadoAjuste;
use App\Enums\Indice;
use App\Models\Contract;
use App\Models\RentAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentAdjustment>
 */
class RentAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        $vigencia = today()->startOfMonth();
        $anterior = 400000;
        $coeficiente = 1.06;

        return [
            'contract_id' => Contract::factory(),
            'vigencia_desde' => $vigencia,
            'monto_anterior' => $anterior,
            'monto_nuevo' => $anterior * $coeficiente,
            'coeficiente' => $coeficiente,
            'indice' => Indice::Ipc,
            'periodo_indice_desde' => $vigencia->copy()->subMonths(4),
            'periodo_indice_hasta' => $vigencia->copy()->subMonth(),
            'valor_indice_desde' => 10000,
            'valor_indice_hasta' => 10600,
            'variacion_porcentual' => 6.0,
            'estado' => EstadoAjuste::Propuesto,
            'aplicado_at' => null,
            'notas' => null,
        ];
    }

    public function aplicado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoAjuste::Aplicado,
            'aplicado_at' => now(),
        ]);
    }
}
