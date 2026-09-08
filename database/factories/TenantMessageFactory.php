<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\TenantMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantMessage>
 */
class TenantMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'periodo' => today()->startOfMonth(),
            'enviado_at' => now(),
            'enviado_por' => null,
        ];
    }
}
