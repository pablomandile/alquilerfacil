<?php

namespace App\Console\Commands;

use App\Services\Cobranzas\GeneradorDeCargos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class GenerarCargos extends Command
{
    protected $signature = 'cargos:generar
                            {--periodo= : Mes a generar en formato AAAA-MM (por defecto, el actual)}';

    protected $description = 'Emite el cargo de alquiler del mes para cada contrato vigente';

    public function handle(GeneradorDeCargos $generador): int
    {
        $periodo = $this->option('periodo')
            ? Date::parse($this->option('periodo').'-01')
            : today();

        $resultados = $generador->generar($periodo);

        $nuevos = $resultados->filter(fn ($r) => $r->nuevo);
        $fallidos = $resultados->reject(fn ($r) => $r->exitoso());

        $this->info(
            "Período {$periodo->translatedFormat('F \d\e Y')}: ".
            "{$nuevos->count()} cargos emitidos, ".
            $resultados->count().' contratos revisados.'
        );

        foreach ($fallidos as $fallo) {
            $this->error("  {$fallo->contract->property->alias}: {$fallo->error}");
        }

        return $fallidos->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
