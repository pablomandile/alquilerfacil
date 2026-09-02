<?php

namespace App\Enums;

enum EstadoCargo: string
{
    case Pendiente = 'pendiente';
    case Parcial = 'parcial';
    case Pagado = 'pagado';
    case Vencido = 'vencido';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Parcial => 'Pago parcial',
            self::Pagado => 'Pagado',
            self::Vencido => 'Vencido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'slate',
            self::Parcial => 'amber',
            self::Pagado => 'emerald',
            self::Vencido => 'rose',
        };
    }
}
