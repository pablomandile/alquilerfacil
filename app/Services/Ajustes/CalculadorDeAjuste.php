<?php

namespace App\Services\Ajustes;

use App\Enums\Indice;
use App\Models\Contract;
use App\Models\IndexValue;
use App\Support\Decimal;
use Carbon\CarbonInterface;

/**
 * Calcula cuánto pasaría a valer un alquiler al aplicarle su índice.
 *
 * Devuelve una propuesta o, si todavía no están publicados los valores que hacen
 * falta, un IndiceNoDisponible. Nunca persiste nada: aplicar el ajuste es
 * decisión del usuario y lo hace AplicadorDeAjuste.
 */
class CalculadorDeAjuste
{
    /**
     * Calcula el ajuste que corresponde a un contrato.
     *
     * @param  CarbonInterface|null  $vigenciaDesde  Por defecto, la fecha de ajuste del contrato.
     */
    public function calcular(Contract $contract, ?CarbonInterface $vigenciaDesde = null): PropuestaDeAjuste|IndiceNoDisponible
    {
        $vigencia = $vigenciaDesde ?? $contract->proximo_ajuste ?? $contract->fecha_inicio;

        [$desde, $hasta] = $contract->indice->esMensual()
            ? $this->ventanaMensual($vigencia, $contract->frecuencia_meses)
            : $this->ventanaDiaria($vigencia, $contract->frecuencia_meses);

        $valorHasta = $this->valorEn($contract->indice, $hasta);
        if ($valorHasta === null) {
            return $this->noDisponible($contract->indice, $hasta);
        }

        $valorDesde = $this->valorEn($contract->indice, $desde);
        if ($valorDesde === null) {
            return $this->noDisponible($contract->indice, $desde);
        }

        // El coeficiente sale de dividir los dos números índice, no de encadenar
        // las variaciones mensuales: así el resultado es exacto para todo el
        // período y no arrastra el redondeo de cada mes.
        $coeficiente = bcdiv((string) $valorHasta->valor, (string) $valorDesde->valor, 10);

        $montoAnterior = (string) $contract->monto_actual;
        $montoNuevo = Decimal::redondear(bcmul($montoAnterior, $coeficiente, 10));

        if ($contract->redondeo > 1) {
            $montoNuevo = Decimal::aMultiploDe($montoNuevo, $contract->redondeo);
        }

        return new PropuestaDeAjuste(
            contract: $contract,
            vigenciaDesde: $vigencia,
            montoAnterior: $montoAnterior,
            montoNuevo: $montoNuevo,
            coeficiente: Decimal::redondear($coeficiente, 8),
            indice: $contract->indice,
            periodoIndiceDesde: $valorDesde->fecha,
            periodoIndiceHasta: $valorHasta->fecha,
            valorIndiceDesde: (string) $valorDesde->valor,
            valorIndiceHasta: (string) $valorHasta->valor,
            variacionPorcentual: Decimal::coeficienteAPorcentaje($coeficiente),
        );
    }

    /**
     * Ventana para índices mensuales (IPC).
     *
     * Un ajuste con vigencia el 1/4 y frecuencia 3 tiene que recoger la inflación
     * de enero, febrero y marzo. Eso es IPC[marzo] / IPC[diciembre]: el índice del
     * mes anterior a la vigencia, dividido por el de `frecuencia` meses antes.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function ventanaMensual(CarbonInterface $vigencia, int $frecuencia): array
    {
        $hasta = $vigencia->copy()->startOfMonth()->subMonth();

        return [$hasta->copy()->subMonths($frecuencia), $hasta];
    }

    /**
     * Ventana para índices diarios (ICL): el valor del día de la vigencia contra
     * el de `frecuencia` meses antes.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function ventanaDiaria(CarbonInterface $vigencia, int $frecuencia): array
    {
        return [$vigencia->copy()->subMonths($frecuencia), $vigencia->copy()];
    }

    /**
     * El IPC exige el valor exacto del mes: tomar uno anterior calcularía sobre un
     * período equivocado. El ICL, en cambio, no publica fines de semana ni
     * feriados, así que ahí sí vale el último valor anterior disponible.
     */
    private function valorEn(Indice $indice, CarbonInterface $fecha): ?IndexValue
    {
        if ($indice->esMensual()) {
            return IndexValue::query()
                ->de($indice)
                ->whereDate('fecha', $fecha->copy()->startOfMonth())
                ->first();
        }

        return IndexValue::vigenteEn($indice, $fecha);
    }

    private function noDisponible(Indice $indice, CarbonInterface $periodo): IndiceNoDisponible
    {
        return new IndiceNoDisponible(
            indice: $indice,
            periodoFaltante: $periodo,
            publicacionEstimada: $this->publicacionEstimada($indice, $periodo),
        );
    }

    /**
     * Cuándo se espera el valor que falta. Para el IPC, el INDEC publica el índice
     * de un mes a mediados del mes siguiente.
     */
    private function publicacionEstimada(Indice $indice, CarbonInterface $periodo): ?CarbonInterface
    {
        if (! $indice->esMensual()) {
            return null;
        }

        return $periodo->copy()
            ->startOfMonth()
            ->addMonth()
            ->setDay((int) config('indices.ipc.dia_de_publicacion', 15));
    }
}
