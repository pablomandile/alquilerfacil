<?php

namespace App\Services\Indices;

use App\Enums\Indice;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Una fuente oficial de la que se bajan valores de un índice.
 *
 * Cada implementación sólo sabe hablar con su API y devolver pares fecha/valor;
 * guardarlos es problema del SincronizadorDeIndices, que es igual para todas.
 */
interface FuenteDeIndice
{
    public function indice(): Indice;

    /**
     * Trae los valores publicados desde una fecha (inclusive). Sin fecha, trae
     * toda la historia disponible.
     *
     * @return Collection<int, array{fecha: CarbonImmutable, valor: string}>
     */
    public function traer(?CarbonInterface $desde = null): Collection;
}
