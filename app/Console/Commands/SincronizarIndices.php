<?php

namespace App\Console\Commands;

use App\Enums\Indice;
use App\Services\Indices\SincronizadorDeIndices;
use Illuminate\Console\Command;

class SincronizarIndices extends Command
{
    protected $signature = 'indices:sincronizar
                            {--fuente= : Sincronizar sólo un índice (ipc o icl)}
                            {--completo : Volver a bajar toda la historia en vez de sólo lo nuevo}';

    protected $description = 'Baja los valores del IPC (INDEC) y del ICL (BCRA) desde sus APIs oficiales';

    public function handle(SincronizadorDeIndices $sincronizador): int
    {
        $fuente = $this->option('fuente');

        if ($fuente !== null && Indice::tryFrom($fuente) === null) {
            $this->error("Fuente desconocida: «{$fuente}». Las válidas son: ipc, icl.");

            return self::FAILURE;
        }

        $resultados = $sincronizador->sincronizar(
            $fuente ? Indice::from($fuente) : null,
            (bool) $this->option('completo'),
        );

        foreach ($resultados as $resultado) {
            $resultado->exitoso()
                ? $this->info($resultado->resumen())
                : $this->error($resultado->resumen());
        }

        // Si una fuente falla (se cayó la API, no hay red), el comando falla para
        // que el scheduler lo reporte, pero las que sí anduvieron ya guardaron.
        return $resultados->every(fn ($r) => $r->exitoso())
            ? self::SUCCESS
            : self::FAILURE;
    }
}
