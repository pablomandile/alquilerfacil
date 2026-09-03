<?php

namespace App\Enums;

enum TipoDocumento: string implements Etiquetable
{
    case Dni = 'dni';
    case Cuit = 'cuit';
    case Cuil = 'cuil';
    case Pasaporte = 'pasaporte';

    public function label(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::Cuit => 'CUIT',
            self::Cuil => 'CUIL',
            self::Pasaporte => 'Pasaporte',
        };
    }
}
