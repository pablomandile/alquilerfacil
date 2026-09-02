<?php

namespace Database\Factories;

use App\Enums\RolUsuario;
use App\Enums\TipoDocumento;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Owner>
 */
class OwnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'nombre' => fake()->name(),
            'tipo_documento' => TipoDocumento::Dni,
            'documento' => (string) fake()->numberBetween(10_000_000, 45_000_000),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->phoneNumber(),
            'cbu' => (string) fake()->numerify(str_repeat('#', 22)),
            'alias_cbu' => fake()->slug(3),
            'notas' => null,
        ];
    }

    /** Propietario con cuenta de acceso a la app. */
    public function conAcceso(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->state([
                'name' => $attributes['nombre'] ?? fake()->name(),
                'rol' => RolUsuario::Propietario,
            ]),
        ]);
    }
}
