<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * El pivote entre propiedad y dueño: guarda el porcentaje de propiedad.
 *
 * @property numeric-string $porcentaje
 */
class PropertyOwner extends Pivot
{
    protected $table = 'property_owner';

    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
        ];
    }
}
