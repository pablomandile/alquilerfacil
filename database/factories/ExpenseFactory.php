<?php

namespace Database\Factories;

use App\Enums\ACargoDe;
use App\Enums\CategoriaGasto;
use App\Enums\TipoGasto;
use App\Models\Expense;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        $periodo = today()->startOfMonth();

        return [
            'property_id' => Property::factory(),
            'contract_id' => null,
            'tipo' => TipoGasto::Servicio,
            'categoria' => CategoriaGasto::Luz,
            'descripcion' => null,
            'periodo' => $periodo,
            'monto' => fake()->numberBetween(15, 120) * 1000,
            'vencimiento' => $periodo->copy()->addDays(20),
            'a_cargo_de' => ACargoDe::Inquilino,
            'pagado' => false,
            'fecha_pago' => null,
            'comprobante_path' => null,
            'notas' => null,
        ];
    }

    /** Gasto extraordinario, que se reparte entre los propietarios. */
    public function extraordinario(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoGasto::Extraordinario,
            'categoria' => CategoriaGasto::Reparacion,
            'a_cargo_de' => ACargoDe::Propietarios,
        ]);
    }

    public function pagado(): static
    {
        return $this->state(fn (array $attributes) => [
            'pagado' => true,
            'fecha_pago' => today(),
        ]);
    }
}
