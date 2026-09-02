<?php

namespace App\Enums;

enum RolUsuario: string
{
    case Admin = 'admin';
    case Propietario = 'propietario';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Propietario => 'Propietario',
        };
    }
}
