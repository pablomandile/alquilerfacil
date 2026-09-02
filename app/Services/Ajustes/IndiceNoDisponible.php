<?php

namespace App\Services\Ajustes;

use App\Enums\Indice;
use Carbon\CarbonInterface;

/**
 * El ajuste no se puede calcular todavía porque falta un valor del índice.
 *
 * Es un resultado esperable, no un error: el INDEC publica el IPC de un mes a
 * mediados del siguiente, así que un ajuste con vigencia el 1° de octubre recién
 * se puede calcular cuando salga el índice de septiembre. Se devuelve esto en vez
 * de una propuesta calculada con los datos que haya, que daría un monto mal.
 */
readonly class IndiceNoDisponible
{
    public function __construct(
        public Indice $indice,
        public CarbonInterface $periodoFaltante,
        public ?CarbonInterface $publicacionEstimada,
    ) {}

    public function motivo(): string
    {
        $periodo = $this->indice->esMensual()
            ? $this->periodoFaltante->translatedFormat('F \d\e Y')
            : $this->periodoFaltante->format('d/m/Y');

        $mensaje = "Falta el {$this->indice->labelCorto()} de {$periodo}";

        if ($this->publicacionEstimada !== null) {
            $mensaje .= ', que se publica alrededor del '
                .$this->publicacionEstimada->translatedFormat('j \d\e F');
        }

        return $mensaje.'.';
    }
}
