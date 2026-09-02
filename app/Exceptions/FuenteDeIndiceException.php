<?php

namespace App\Exceptions;

use App\Enums\Indice;
use RuntimeException;
use Throwable;

class FuenteDeIndiceException extends RuntimeException
{
    public static function noRespondio(Indice $indice, string $motivo, ?Throwable $previa = null): self
    {
        return new self(
            "No se pudo consultar el {$indice->labelCorto()}: {$motivo}",
            previous: $previa
        );
    }

    public static function respuestaInesperada(Indice $indice): self
    {
        return new self(
            "La API del {$indice->labelCorto()} respondió en un formato inesperado. ".
            'Puede haber cambiado la estructura del servicio.'
        );
    }
}
