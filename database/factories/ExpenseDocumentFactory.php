<?php

namespace Database\Factories;

use App\Enums\TipoDocumentoGasto;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseDocument>
 */
class ExpenseDocumentFactory extends Factory
{
    public function definition(): array
    {
        $nombre = fake()->word().'.pdf';

        return [
            'expense_id' => Expense::factory(),
            'tipo' => fake()->randomElement(TipoDocumentoGasto::cases()),
            'nombre_original' => $nombre,
            'path' => 'gastos/0/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'tamano' => fake()->numberBetween(20_000, 4_000_000),
            'subido_por' => null,
        ];
    }
}
