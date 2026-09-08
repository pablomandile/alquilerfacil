<?php

namespace Database\Factories;

use App\Models\AdminThread;
use App\Models\AdminThreadEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminThreadEntry>
 */
class AdminThreadEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admin_thread_id' => AdminThread::factory(),
            'fecha' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'detalle' => fake()->paragraph(),
            'registrado_por' => null,
        ];
    }
}
