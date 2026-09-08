<?php

namespace App\Enums;

/**
 * Un tema con la administración está abierto mientras se le hace seguimiento y
 * pasa a resuelto cuando se cierra.
 */
enum EstadoTemaAdmin: string implements Etiquetable
{
    case Abierto = 'abierto';
    case Resuelto = 'resuelto';

    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::Resuelto => 'Resuelto',
        };
    }
}
