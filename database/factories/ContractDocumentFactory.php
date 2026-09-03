<?php

namespace Database\Factories;

use App\Enums\TipoDocumentoContrato;
use App\Models\Contract;
use App\Models\ContractDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractDocument>
 */
class ContractDocumentFactory extends Factory
{
    public function definition(): array
    {
        $nombre = fake()->word().'.pdf';

        return [
            'contract_id' => Contract::factory(),
            'tipo' => fake()->randomElement(TipoDocumentoContrato::cases()),
            'nota' => null,
            'nombre_original' => $nombre,
            'path' => 'contratos/0/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'tamano' => fake()->numberBetween(20_000, 4_000_000),
            'subido_por' => null,
        ];
    }
}
