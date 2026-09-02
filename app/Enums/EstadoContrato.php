<?php

namespace App\Enums;

enum EstadoContrato: string
{
    case Activo = 'activo';
    case Finalizado = 'finalizado';
    case Rescindido = 'rescindido';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Finalizado => 'Finalizado',
            self::Rescindido => 'Rescindido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Activo => 'emerald',
            self::Finalizado => 'slate',
            self::Rescindido => 'rose',
        };
    }
}
