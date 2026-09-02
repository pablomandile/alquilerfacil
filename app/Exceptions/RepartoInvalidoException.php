<?php

namespace App\Exceptions;

use App\Models\Property;
use RuntimeException;

class RepartoInvalidoException extends RuntimeException
{
    public static function sinPropietarios(Property $property): self
    {
        return new self(
            "La propiedad «{$property->alias}» no tiene propietarios cargados, ".
            'así que no hay entre quiénes repartir.'
        );
    }

    public static function porcentajesNoSuman100(Property $property, string $suma): self
    {
        return new self(
            "Los porcentajes de propiedad de «{$property->alias}» suman {$suma}% en vez de 100%. ".
            'Corregilos antes de repartir.'
        );
    }
}
