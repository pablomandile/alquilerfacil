<?php

namespace Database\Factories;

use App\Enums\CategoriaTemaAdmin;
use App\Enums\EstadoTemaAdmin;
use App\Models\AdminThread;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminThread>
 */
class AdminThreadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'titulo' => fake()->sentence(4),
            'categoria' => fake()->randomElement(CategoriaTemaAdmin::cases()),
            'estado' => EstadoTemaAdmin::Abierto,
            'resuelto_at' => null,
        ];
    }

    public function resuelto(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoTemaAdmin::Resuelto,
            'resuelto_at' => now(),
        ]);
    }
}
