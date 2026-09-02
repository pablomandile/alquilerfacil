<?php

namespace App\Services\Ajustes;

use App\Enums\EstadoAjuste;
use App\Models\RentAdjustment;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Guarda, aplica y rechaza los ajustes propuestos.
 *
 * Separado del CalculadorDeAjuste a propósito: calcular es una cuenta sin
 * efectos, aplicar cambia el valor del alquiler y sólo pasa cuando el usuario lo
 * confirma.
 */
class AplicadorDeAjuste
{
    /**
     * Guarda la propuesta como pendiente de decisión.
     *
     * Si ya había una propuesta para esa misma vigencia se actualiza en vez de
     * duplicarse, así se puede recalcular cuando el INDEC publica un índice nuevo.
     * Una que ya fue aplicada o rechazada no se toca.
     */
    public function proponer(PropuestaDeAjuste $propuesta): RentAdjustment
    {
        $existente = RentAdjustment::query()
            ->where('contract_id', $propuesta->contract->id)
            ->whereDate('vigencia_desde', $propuesta->vigenciaDesde)
            ->first();

        if ($existente !== null && ! $existente->estaPropuesto()) {
            return $existente;
        }

        return RentAdjustment::query()->updateOrCreate(
            [
                'contract_id' => $propuesta->contract->id,
                'vigencia_desde' => $propuesta->vigenciaDesde,
            ],
            [...$propuesta->aAtributos(), 'estado' => EstadoAjuste::Propuesto],
        );
    }

    /**
     * Aplica el ajuste: pasa a ser el nuevo valor del alquiler.
     *
     * @param  string|null  $montoEditado  Monto pactado, si difiere del calculado.
     */
    public function aplicar(RentAdjustment $ajuste, ?string $montoEditado = null): RentAdjustment
    {
        return DB::transaction(function () use ($ajuste, $montoEditado) {
            $contract = $ajuste->contract;

            if ($montoEditado !== null) {
                // Se pisa el monto pero se dejan intactos los valores del índice:
                // sirven para ver después cuánto daba la cuenta y cuánto se pactó.
                $ajuste->monto_nuevo = Decimal::redondear($montoEditado);
                $ajuste->notas = trim(($ajuste->notas ?? '')."\nMonto ajustado a mano al aplicar.");
            }

            $ajuste->estado = EstadoAjuste::Aplicado;
            $ajuste->aplicado_at = now();
            $ajuste->save();

            $contract->update([
                'monto_actual' => $ajuste->monto_nuevo,
                'proximo_ajuste' => $ajuste->vigencia_desde
                    ->copy()
                    ->addMonths($contract->frecuencia_meses),
            ]);

            return $ajuste->fresh();
        });
    }

    /**
     * Descarta el ajuste y corre la fecha del próximo.
     *
     * Correrla es lo que evita que la app vuelva a proponer lo mismo cada vez. La
     * consecuencia es que la inflación de ese período no se recupera más adelante:
     * rechazar significa que ese trimestre no hubo aumento.
     */
    public function rechazar(RentAdjustment $ajuste, ?string $motivo = null): RentAdjustment
    {
        return DB::transaction(function () use ($ajuste, $motivo) {
            $contract = $ajuste->contract;

            $ajuste->estado = EstadoAjuste::Rechazado;

            if ($motivo !== null && $motivo !== '') {
                $ajuste->notas = trim(($ajuste->notas ?? '')."\n".$motivo);
            }

            $ajuste->save();

            $contract->update([
                'proximo_ajuste' => $ajuste->vigencia_desde
                    ->copy()
                    ->addMonths($contract->frecuencia_meses),
            ]);

            return $ajuste->fresh();
        });
    }
}
