<?php

namespace App\Enums;

/**
 * Índice usado para actualizar el alquiler. Es a la vez el índice elegido en el
 * contrato y la fuente de los valores guardados en `index_values`.
 */
enum Indice: string
{
    case Ipc = 'ipc';
    case Icl = 'icl';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Ipc => 'IPC Nacional (INDEC)',
            self::Icl => 'ICL (BCRA)',
            self::Manual => 'Manual',
        };
    }

    public function labelCorto(): string
    {
        return match ($this) {
            self::Ipc => 'IPC',
            self::Icl => 'ICL',
            self::Manual => 'Manual',
        };
    }

    /**
     * El IPC es mensual (un valor por mes, con fecha el día 1); el ICL es diario.
     * Determina cómo se buscan los valores al calcular un ajuste.
     */
    public function esMensual(): bool
    {
        return $this === self::Ipc;
    }

    /** Índices que se sincronizan automáticamente desde una API pública. */
    public function seSincroniza(): bool
    {
        return $this !== self::Manual;
    }
}
