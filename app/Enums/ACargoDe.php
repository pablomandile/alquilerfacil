<?php

namespace App\Enums;

/**
 * Quién soporta económicamente un gasto. Los que quedan a cargo de los
 * propietarios se reparten entre ellos según su porcentaje de propiedad.
 */
enum ACargoDe: string implements Etiquetable
{
    case Inquilino = 'inquilino';
    case Propietarios = 'propietarios';

    public function label(): string
    {
        return match ($this) {
            self::Inquilino => 'Inquilino',
            self::Propietarios => 'Propietarios',
        };
    }

    public function seReparte(): bool
    {
        return $this === self::Propietarios;
    }
}
