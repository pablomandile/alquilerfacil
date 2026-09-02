<?php

namespace Database\Factories;

use App\Enums\EstadoCargo;
use App\Models\Contract;
use App\Models\RentCharge;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<RentCharge>
 */
class RentChargeFactory extends Factory
{
    public function definition(): array
    {
        $periodo = today()->startOfMonth();

        return [
            'contract_id' => Contract::factory(),
            'periodo' => $periodo,
            'monto' => fake()->numberBetween(250, 900) * 1000,
            'vencimiento' => $periodo->copy()->setDay(10),
            'estado' => EstadoCargo::Pendiente,
            'notas' => null,
        ];
    }

    public function delPeriodo(CarbonInterface|string $periodo): static
    {
        $periodo = Date::parse($periodo)->startOfMonth();

        return $this->state(fn (array $attributes) => [
            'periodo' => $periodo,
            'vencimiento' => $periodo->copy()->setDay(10),
        ]);
    }

    public function conMonto(float|int|string $monto): static
    {
        return $this->state(fn (array $attributes) => ['monto' => $monto]);
    }
}
