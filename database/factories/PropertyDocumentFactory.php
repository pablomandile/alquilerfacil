<?php

namespace Database\Factories;

use App\Enums\TipoDocumentoPropiedad;
use App\Models\Property;
use App\Models\PropertyDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyDocument>
 */
class PropertyDocumentFactory extends Factory
{
    public function definition(): array
    {
        $nombre = fake()->word().'.pdf';

        return [
            'property_id' => Property::factory(),
            'tipo' => fake()->randomElement(TipoDocumentoPropiedad::cases()),
            'nota' => null,
            'nombre_original' => $nombre,
            'path' => 'propiedades/0/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'tamano' => fake()->numberBetween(20_000, 4_000_000),
            'subido_por' => null,
        ];
    }
}
