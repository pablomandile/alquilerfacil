<?php

namespace App\Support;

use App\Enums\Etiquetable;

/**
 * Convierte los enums del dominio en opciones para los selects del front.
 *
 * Las etiquetas viven en el enum (`label()`), así que la pantalla y la base
 * hablan siempre de lo mismo y no hay que traducir en dos lados.
 */
class Opciones
{
    /**
     * @param  class-string<\BackedEnum&Etiquetable>  $enum
     * @return list<array{value: string, label: string}>
     */
    public static function de(string $enum): array
    {
        return array_map(
            fn (\BackedEnum&Etiquetable $caso) => [
                'value' => (string) $caso->value,
                'label' => $caso->label(),
            ],
            $enum::cases(),
        );
    }
}
