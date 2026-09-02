<?php

namespace App\Console\Commands;

use App\Services\Ajustes\GeneradorDePropuestas;
use App\Services\Ajustes\PropuestaDeAjuste;
use Illuminate\Console\Command;

class ProponerAjustes extends Command
{
    protected $signature = 'ajustes:proponer';

    protected $description = 'Calcula los ajustes de los contratos que ya llegaron a su fecha de actualización';

    public function handle(GeneradorDePropuestas $generador): int
    {
        $resultados = $generador->generar();

        if ($resultados->isEmpty()) {
            $this->info('No hay contratos con ajuste pendiente.');

            return self::SUCCESS;
        }

        foreach ($resultados as $resultado) {
            if ($resultado instanceof PropuestaDeAjuste) {
                $this->info(sprintf(
                    '%s: %s -> %s (%+.2f %%)',
                    $resultado->contract->property->alias,
                    number_format((float) $resultado->montoAnterior, 2, ',', '.'),
                    number_format((float) $resultado->montoNuevo, 2, ',', '.'),
                    (float) $resultado->variacionPorcentual,
                ));

                continue;
            }

            // No es un error: el índice todavía no salió. Se vuelve a intentar
            // en la próxima corrida.
            $this->line("  <fg=yellow>En espera</> {$resultado->motivo()}");
        }

        return self::SUCCESS;
    }
}
