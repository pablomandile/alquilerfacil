<?php

namespace App\Enums;

enum TipoGasto: string implements Etiquetable
{
    case Servicio = 'servicio';
    case Expensas = 'expensas';
    case Impuesto = 'impuesto';
    case Extraordinario = 'extraordinario';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Servicio => 'Servicio',
            self::Expensas => 'Expensas',
            self::Impuesto => 'Impuesto',
            self::Extraordinario => 'Gasto extraordinario',
            self::Otro => 'Otro',
        };
    }

    /**
     * Por defecto, quién soporta el gasto. El usuario lo puede cambiar en cada
     * gasto: es sólo el valor sugerido al cargarlo.
     */
    public function aCargoDePorDefecto(): ACargoDe
    {
        return match ($this) {
            self::Extraordinario, self::Impuesto => ACargoDe::Propietarios,
            default => ACargoDe::Inquilino,
        };
    }
}
