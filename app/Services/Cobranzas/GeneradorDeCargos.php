<?php

namespace App\Services\Cobranzas;

use App\Exceptions\RepartoInvalidoException;
use App\Models\Contract;
use App\Models\RentCharge;
use App\Services\Repartos\RepartidorEntreDuenos;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Emite el cargo de alquiler de cada mes para los contratos vigentes.
 *
 * Es idempotente: el unique(contract_id, periodo) hace que volver a correrlo no
 * duplique nada, así que se puede ejecutar sin miedo si el cron falló un día.
 */
class GeneradorDeCargos
{
    public function __construct(private readonly RepartidorEntreDuenos $repartidor) {}

    /**
     * @return Collection<int, ResultadoDeGeneracion>
     */
    public function generar(?CarbonInterface $periodo = null): Collection
    {
        $periodo = ($periodo ?? today())->copy()->startOfMonth();

        return Contract::query()
            ->activos()
            // Un contrato que arranca el mes que viene o que ya terminó no genera
            // cargo en este período.
            ->whereDate('fecha_inicio', '<=', $periodo->copy()->endOfMonth())
            ->whereDate('fecha_fin', '>=', $periodo)
            ->with('property.owners')
            ->get()
            ->map(fn (Contract $contract) => $this->generarPara($contract, $periodo));
    }

    public function generarPara(Contract $contract, CarbonInterface $periodo): ResultadoDeGeneracion
    {
        $periodo = $periodo->copy()->startOfMonth();

        $existente = RentCharge::query()
            ->where('contract_id', $contract->id)
            ->whereDate('periodo', $periodo)
            ->first();

        if ($existente !== null) {
            return ResultadoDeGeneracion::yaExistia($contract, $existente);
        }

        try {
            $cargo = DB::transaction(function () use ($contract, $periodo) {
                $cargo = RentCharge::query()->create([
                    'contract_id' => $contract->id,
                    // Se congela el monto vigente: si más adelante se aplica un
                    // ajuste, los cargos ya emitidos no cambian.
                    'monto' => $contract->monto_actual,
                    'periodo' => $periodo,
                    'vencimiento' => $this->vencimiento($contract, $periodo),
                ]);

                $this->repartidor->repartir($cargo);

                return $cargo;
            });
        } catch (RepartoInvalidoException $e) {
            // Sin dueños bien cargados no se puede saber a quién le corresponde
            // cada peso, así que no se emite el cargo y se avisa cuál falla.
            return ResultadoDeGeneracion::fallo($contract, $e->getMessage());
        }

        return ResultadoDeGeneracion::creado($contract, $cargo);
    }

    /**
     * El día de vencimiento pactado, recortado al último día del mes: un contrato
     * que vence el 31 tiene que vencer el 30 en los meses de 30 días.
     */
    private function vencimiento(Contract $contract, CarbonInterface $periodo): CarbonInterface
    {
        return $periodo->copy()->setDay(
            min($contract->dia_vencimiento, $periodo->daysInMonth)
        );
    }
}
