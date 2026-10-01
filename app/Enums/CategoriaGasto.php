<?php

namespace App\Enums;

enum CategoriaGasto: string implements Etiquetable
{
    case Luz = 'luz';
    case Agua = 'agua';
    case Gas = 'gas';
    case Expensas = 'expensas';
    case Abl = 'abl';
    case Internet = 'internet';
    case Reparacion = 'reparacion';
    case Seguro = 'seguro';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Luz => 'Luz',
            self::Agua => 'Agua',
            self::Gas => 'Gas',
            self::Expensas => 'Expensas',
            self::Abl => 'ABL / Inmobiliario',
            self::Internet => 'Internet / Cable',
            self::Reparacion => 'Reparación',
            self::Seguro => 'Seguro',
            self::Otro => 'Otro',
        };
    }

    /** El tipo con que se carga un gasto de esta categoría si no se elige otro. */
    public function tipoPorDefecto(): TipoGasto
    {
        return match ($this) {
            self::Luz, self::Agua, self::Gas, self::Internet => TipoGasto::Servicio,
            self::Expensas => TipoGasto::Expensas,
            self::Abl => TipoGasto::Impuesto,
            self::Reparacion => TipoGasto::Extraordinario,
            self::Seguro, self::Otro => TipoGasto::Otro,
        };
    }
}
