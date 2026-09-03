<?php

namespace App\Services\Ajustes;

use App\Enums\Indice;
use App\Models\Contract;
use Carbon\CarbonInterface;

/**
 * Un ajuste calculado y listo para ofrecer, todavía sin guardar ni aplicar.
 *
 * La app calcula y propone; aplicarlo es siempre una decisión del usuario.
 */
readonly class PropuestaDeAjuste
{
    /**
     * @param  numeric-string  $montoAnterior
     * @param  numeric-string  $montoNuevo
     * @param  numeric-string  $coeficiente
     * @param  numeric-string  $valorIndiceDesde
     * @param  numeric-string  $valorIndiceHasta
     * @param  numeric-string  $variacionPorcentual
     */
    public function __construct(
        public Contract $contract,
        public CarbonInterface $vigenciaDesde,
        public string $montoAnterior,
        public string $montoNuevo,
        public string $coeficiente,
        public Indice $indice,
        public CarbonInterface $periodoIndiceDesde,
        public CarbonInterface $periodoIndiceHasta,
        public string $valorIndiceDesde,
        public string $valorIndiceHasta,
        public string $variacionPorcentual,
    ) {}

    /** @return numeric-string */
    public function diferencia(): string
    {
        return bcsub($this->montoNuevo, $this->montoAnterior, 2);
    }

    /** El alquiler baja: pasa si hubo deflación en el período. */
    public function esBaja(): bool
    {
        return bccomp($this->montoNuevo, $this->montoAnterior, 2) < 0;
    }

    /** @return array<string, mixed> Listo para crear el RentAdjustment. */
    public function aAtributos(): array
    {
        return [
            'contract_id' => $this->contract->id,
            'vigencia_desde' => $this->vigenciaDesde,
            'monto_anterior' => $this->montoAnterior,
            'monto_nuevo' => $this->montoNuevo,
            'coeficiente' => $this->coeficiente,
            'indice' => $this->indice,
            'periodo_indice_desde' => $this->periodoIndiceDesde,
            'periodo_indice_hasta' => $this->periodoIndiceHasta,
            'valor_indice_desde' => $this->valorIndiceDesde,
            'valor_indice_hasta' => $this->valorIndiceHasta,
            'variacion_porcentual' => $this->variacionPorcentual,
        ];
    }
}
