<?php

namespace Database\Factories;

use App\Enums\Indice;
use App\Models\IndexValue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<IndexValue>
 */
class IndexValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fuente' => Indice::Ipc,
            'fecha' => today()->startOfMonth(),
            'valor' => fake()->randomFloat(4, 1000, 15000),
            'variacion_mensual' => fake()->randomFloat(6, 0.005, 0.05),
            'sincronizado_at' => now(),
        ];
    }

    public function ipc(CarbonInterface|string $fecha, float|string $valor): static
    {
        return $this->state(fn (array $attributes) => [
            'fuente' => Indice::Ipc,
            'fecha' => Date::parse($fecha)->startOfMonth(),
            'valor' => $valor,
        ]);
    }

    public function icl(CarbonInterface|string $fecha, float|string $valor): static
    {
        return $this->state(fn (array $attributes) => [
            'fuente' => Indice::Icl,
            'fecha' => Date::parse($fecha),
            'valor' => $valor,
        ]);
    }
}
