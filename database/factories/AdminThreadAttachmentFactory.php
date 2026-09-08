<?php

namespace Database\Factories;

use App\Models\AdminThreadAttachment;
use App\Models\AdminThreadEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminThreadAttachment>
 */
class AdminThreadAttachmentFactory extends Factory
{
    public function definition(): array
    {
        $nombre = fake()->word().'.pdf';

        return [
            'admin_thread_entry_id' => AdminThreadEntry::factory(),
            'nombre_original' => $nombre,
            'path' => 'propiedades/0/administracion/0/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'tamano' => fake()->numberBetween(20_000, 4_000_000),
            'subido_por' => null,
        ];
    }
}
