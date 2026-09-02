<?php

namespace Database\Factories;

use App\Enums\MedioPago;
use App\Models\Payment;
use App\Models\RentCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rent_charge_id' => RentCharge::factory(),
            'fecha' => today(),
            'monto' => fake()->numberBetween(250, 900) * 1000,
            'medio' => MedioPago::Transferencia,
            'referencia' => null,
            'notas' => null,
        ];
    }

    public function de(float|int|string $monto): static
    {
        return $this->state(fn (array $attributes) => ['monto' => $monto]);
    }
}
