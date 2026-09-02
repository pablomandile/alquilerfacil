<?php

namespace App\Services\Indices;

use App\Enums\Indice;
use App\Exceptions\FuenteDeIndiceException;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * ICL (Índice para Contratos de Locación) del BCRA, base 30/6/2020 = 1.
 *
 * Responde `{"results": [{"idVariable": 40, "detalle": [{"fecha", "valor"}]}]}`.
 * A diferencia del IPC, es diario: hay un valor por día hábil.
 */
class BcraIclSource implements FuenteDeIndice
{
    use ConsultaHttp;

    /** Tope por consulta que impone la API del BCRA. */
    private const LIMITE = 1000;

    public function indice(): Indice
    {
        return Indice::Icl;
    }

    public function traer(?CarbonInterface $desde = null): Collection
    {
        $desde ??= Date::parse(config('indices.icl.desde_inicial'));
        $hasta = today();

        $valores = collect();
        $offset = 0;

        // La serie completa supera el tope de 1000 por consulta, así que se pagina
        // hasta juntar todo lo que la API dice que hay.
        do {
            $respuesta = $this->pedir(config('indices.icl.url'), [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'limit' => self::LIMITE,
                'offset' => $offset,
            ]);

            $pagina = $this->detalleDe($respuesta);
            $valores = $valores->concat($pagina);

            $total = (int) data_get($respuesta, 'metadata.resultset.count', 0);
            $offset += self::LIMITE;
        } while ($pagina->isNotEmpty() && $valores->count() < $total);

        return $valores->sortBy('fecha')->values();
    }

    /**
     * @return Collection<int, array{fecha: CarbonInterface, valor: string}>
     */
    private function detalleDe(array $respuesta): Collection
    {
        $detalle = data_get($respuesta, 'results.0.detalle');

        if (! is_array($detalle)) {
            throw FuenteDeIndiceException::respuestaInesperada($this->indice());
        }

        return collect($detalle)
            ->filter(fn ($fila) => isset($fila['fecha'], $fila['valor']))
            ->map(fn (array $fila) => [
                'fecha' => Date::parse($fila['fecha'])->startOfDay(),
                'valor' => $this->comoDecimal($fila['valor']),
            ])
            ->values();
    }
}
