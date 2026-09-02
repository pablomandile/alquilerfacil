<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
*/

// Diario porque el ICL es diario. El IPC simplemente no va a traer nada nuevo la
// mayoría de los días, y el día que el INDEC publica, entra solo.
Schedule::command('indices:sincronizar')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// Después de sincronizar, para que un contrato que estaba esperando el índice
// tenga su propuesta el mismo día en que sale.
Schedule::command('ajustes:proponer')
    ->dailyAt('09:15')
    ->withoutOverlapping();

// Los cargos del mes, el día 1.
Schedule::command('cargos:generar')
    ->monthlyOn(1, '06:00')
    ->withoutOverlapping();
