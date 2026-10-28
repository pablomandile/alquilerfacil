<?php

namespace App\Console\Commands;

use App\Models\IndexValue;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Vacía la base y vuelve a sembrar la demo. Corre todas las noches en la copia
 * de pablomandile (ver `routes/console.php`).
 *
 * Los índices son los reales de INDEC y BCRA. Se guardan antes de vaciar y se
 * vuelven a poner después, y recién ahí se sincroniza lo nuevo: así una noche en
 * que alguna API no responde, la demo sigue con los índices del día anterior en
 * vez de quedarse sin ajustes.
 *
 * ⚠️ Borra **todo** el disco privado, no carpetas sueltas: ahí van los archivos
 * de contratos, propiedades, gastos, pagos y administración, una carpeta por
 * tabla. Una lista de carpetas dejaría afuera la del próximo dueño de archivos.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Borra todos los datos y resiembra la demo (solo con APP_DEMO=true)';

    public function handle(): int
    {
        // Sin el flag no se toca nada: este comando borra la base entera.
        if (! config('demo.enabled')) {
            $this->error('La demo no está activa (APP_DEMO). No se borró nada.');

            return self::FAILURE;
        }

        $indices = IndexValue::query()->get()
            ->map(fn (IndexValue $v) => $v->getAttributes())
            ->all();

        // En producción `AppServiceProvider` prohíbe `migrate:fresh`, y la demo
        // corre con APP_ENV=production. Se levanta solo acá, que ya exige el flag.
        DB::prohibitDestructiveCommands(false);

        // Si la base no se vació, no se sigue: sembrar encima mezclaría la demo
        // con lo que hubiera, y borrar los archivos dejaría filas sin su archivo.
        if ($this->call('migrate:fresh', ['--force' => true]) !== self::SUCCESS) {
            $this->error('No se pudo vaciar la base. No se tocó nada más.');

            return self::FAILURE;
        }

        $disco = Storage::disk('local');
        foreach ($disco->directories() as $carpeta) {
            $disco->deleteDirectory($carpeta);
        }
        $disco->delete($disco->files());

        // Lo que los visitantes hicieron durante el día no tiene por qué quedar.
        $log = storage_path('logs/laravel.log');
        if (File::exists($log)) {
            File::put($log, '');
        }

        foreach (array_chunk($indices, 500) as $tanda) {
            IndexValue::query()->insert($tanda);
        }

        // Si una API no responde, sigue con lo que había.
        if ($this->call('indices:sincronizar') !== self::SUCCESS) {
            $this->warn('No se pudo sincronizar algún índice: la demo sigue con los valores guardados.');
        }

        if (! IndexValue::query()->exists()) {
            $this->error('No hay índices (ni guardados ni sincronizados): no se siembra la demo.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
