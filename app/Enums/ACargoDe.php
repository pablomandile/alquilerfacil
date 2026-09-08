<?php

namespace App\Enums;

/**
 * Quién soporta económicamente un gasto. La parte que queda a cargo de los
 * propietarios se reparte entre ellos según su porcentaje de propiedad; con
 * `Mitades` esa parte es la mitad del gasto y la otra mitad la paga el inquilino.
 */
enum ACargoDe: string implements Etiquetable
{
    case Inquilino = 'inquilino';
    case Propietarios = 'propietarios';
    case Mitades = 'mitades';

    public function label(): string
    {
        return match ($this) {
            self::Inquilino => 'Inquilino',
            self::Propietarios => 'Propietarios',
            self::Mitades => 'Los dos (mitad y mitad)',
        };
    }

    /** ¿Hay una parte que se reparte entre los propietarios? */
    public function seReparte(): bool
    {
        return $this !== self::Inquilino;
    }

    /** ¿El inquilino soporta el gasto, entero o a medias? */
    public function afectaAlInquilino(): bool
    {
        return $this !== self::Propietarios;
    }
}
