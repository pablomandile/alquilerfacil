<?php

namespace Database\Factories;

use App\Enums\TipoDocumento;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'tipo_documento' => TipoDocumento::Dni,
            'documento' => (string) fake()->numberBetween(10_000_000, 45_000_000),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->phoneNumber(),
            'notas' => null,
        ];
    }
}
