<?php

namespace App\Enums;

enum TipoPropiedad: string
{
    case Departamento = 'departamento';
    case Casa = 'casa';
    case Local = 'local';
    case Oficina = 'oficina';
    case Cochera = 'cochera';
    case Galpon = 'galpon';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Departamento => 'Departamento',
            self::Casa => 'Casa',
            self::Local => 'Local comercial',
            self::Oficina => 'Oficina',
            self::Cochera => 'Cochera',
            self::Galpon => 'Galpón',
            self::Otro => 'Otro',
        };
    }
}
