<?php

namespace Database\Factories;

use App\Enums\EstadoPropiedad;
use App\Enums\TipoPropiedad;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        $calle = fake()->randomElement([
            'Av. Cabildo', 'Av. Santa Fe', 'Gorriti', 'Thames', 'Av. Rivadavia',
            'Bulnes', 'Malabia', 'Av. Córdoba', 'Juramento', 'Olazábal',
        ]);
        $numero = (string) fake()->numberBetween(100, 4500);

        return [
            'alias' => "{$calle} {$numero}",
            'tipo' => TipoPropiedad::Departamento,
            'estado' => EstadoPropiedad::Disponible,
            'calle' => $calle,
            'numero' => $numero,
            'piso' => (string) fake()->numberBetween(1, 12),
            'depto' => fake()->randomLetter(),
            'localidad' => 'Ciudad Autónoma de Buenos Aires',
            'provincia' => 'CABA',
            'codigo_postal' => 'C'.fake()->numberBetween(1000, 1499).fake()->lexify('???'),
            'ambientes' => fake()->numberBetween(1, 5),
            'superficie_m2' => fake()->numberBetween(30, 140),
            'partida_inmobiliaria' => (string) fake()->numberBetween(100000, 999999),
            'notas' => null,
        ];
    }

    public function alquilada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoPropiedad::Alquilada,
        ]);
    }
}
