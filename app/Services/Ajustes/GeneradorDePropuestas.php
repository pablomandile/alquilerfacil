<?php

namespace App\Services\Ajustes;

use App\Models\Contract;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Recorre los contratos cuya fecha de ajuste ya llegó y deja una propuesta lista
 * para que el usuario la revise.
 *
 * Sólo propone: no cambia ningún alquiler. Se vuelve a correr cada día porque un
 * contrato puede quedar esperando a que el INDEC publique el índice que falta, y
 * cuando sale, la propuesta aparece sola.
 */
class GeneradorDePropuestas
{
    public function __construct(
        private readonly CalculadorDeAjuste $calculador,
        private readonly AplicadorDeAjuste $aplicador,
    ) {}

    /**
     * @return Collection<int, PropuestaDeAjuste|IndiceNoDisponible>
     */
    public function generar(?CarbonInterface $hasta = null): Collection
    {
        return Contract::query()
            ->conAjustePendiente($hasta)
            ->with('property')
            ->get()
            ->map(function (Contract $contract) {
                $resultado = $this->calculador->calcular($contract);

                if ($resultado instanceof PropuestaDeAjuste) {
                    $this->aplicador->proponer($resultado);
                }

                return $resultado;
            });
    }
}
