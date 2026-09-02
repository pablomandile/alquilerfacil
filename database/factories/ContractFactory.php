<?php

namespace Database\Factories;

use App\Enums\EstadoContrato;
use App\Enums\Indice;
use App\Models\Contract;
use App\Models\Property;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    public function definition(): array
    {
        $inicio = today()->subMonths(fake()->numberBetween(3, 18))->startOfMonth();
        $monto = fake()->numberBetween(250, 900) * 1000;

        return [
            'property_id' => Property::factory(),
            'tenant_id' => Tenant::factory(),
            'fecha_inicio' => $inicio,
            'fecha_fin' => $inicio->copy()->addYears(2),
            'monto_base' => $monto,
            'monto_actual' => $monto,
            'dia_vencimiento' => 10,
            'deposito' => $monto,
            'indice' => Indice::Ipc,
            'frecuencia_meses' => 3,
            'proximo_ajuste' => $inicio->copy()->addMonths(3),
            'redondeo' => 0,
            'estado' => EstadoContrato::Activo,
            'notas' => null,
        ];
    }

    /** Contrato que arranca en una fecha concreta, útil para fijar los ajustes. */
    public function desde(CarbonInterface $inicio): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => $inicio,
            'fecha_fin' => $inicio->copy()->addYears(2),
            'proximo_ajuste' => $inicio->copy()->addMonths($attributes['frecuencia_meses'] ?? 3),
        ]);
    }

    public function conMonto(float|int|string $monto): static
    {
        return $this->state(fn (array $attributes) => [
            'monto_base' => $monto,
            'monto_actual' => $monto,
        ]);
    }

    public function porIcl(): static
    {
        return $this->state(fn (array $attributes) => [
            'indice' => Indice::Icl,
        ]);
    }

    public function finalizado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoContrato::Finalizado,
        ]);
    }
}
