<?php

namespace App\Services\Indices;

use App\Enums\Indice;
use Carbon\CarbonInterface;

readonly class ResultadoSincronizacion
{
    public function __construct(
        public Indice $indice,
        public int $recibidos,
        public int $nuevos,
        public ?CarbonInterface $ultimaFecha,
        public ?string $error = null,
    ) {}

    public static function fallo(Indice $indice, string $error): self
    {
        return new self($indice, 0, 0, null, $error);
    }

    public function exitoso(): bool
    {
        return $this->error === null;
    }

    public function resumen(): string
    {
        if (! $this->exitoso()) {
            return "{$this->indice->labelCorto()}: {$this->error}";
        }

        $ultima = $this->ultimaFecha?->format('d/m/Y') ?? 'sin datos';

        return "{$this->indice->labelCorto()}: {$this->recibidos} valores consultados, ".
            "{$this->nuevos} nuevos (último: {$ultima})";
    }
}
