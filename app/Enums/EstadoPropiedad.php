<?php

namespace App\Enums;

enum EstadoPropiedad: string
{
    case Disponible = 'disponible';
    case Alquilada = 'alquilada';
    case EnRefaccion = 'en_refaccion';

    public function label(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Alquilada => 'Alquilada',
            self::EnRefaccion => 'En refacción',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Disponible => 'amber',
            self::Alquilada => 'emerald',
            self::EnRefaccion => 'slate',
        };
    }
}
