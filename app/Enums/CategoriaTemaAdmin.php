<?php

namespace App\Enums;

/**
 * Cómo se clasifica un tema tratado con la administración del inmueble
 * (el consorcio, el municipio, un prestador de servicios, etc.).
 */
enum CategoriaTemaAdmin: string implements Etiquetable
{
    case Reclamo = 'reclamo';
    case Problema = 'problema';
    case Consulta = 'consulta';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Reclamo => 'Reclamo',
            self::Problema => 'Problema',
            self::Consulta => 'Consulta',
            self::Otro => 'Otro',
        };
    }
}
