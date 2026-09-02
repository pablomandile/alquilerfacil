<?php

namespace App\Enums;

enum EstadoAjuste: string
{
    case Propuesto = 'propuesto';
    case Aplicado = 'aplicado';
    case Rechazado = 'rechazado';

    public function label(): string
    {
        return match ($this) {
            self::Propuesto => 'Propuesto',
            self::Aplicado => 'Aplicado',
            self::Rechazado => 'Rechazado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Propuesto => 'amber',
            self::Aplicado => 'emerald',
            self::Rechazado => 'slate',
        };
    }
}
