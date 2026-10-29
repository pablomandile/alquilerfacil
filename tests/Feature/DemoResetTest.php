<?php

namespace Tests\Feature;

use App\Models\IndexValue;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `demo:reset` de punta a punta. Va aparte de DemoModeTest porque corre un
 * `migrate:fresh`, que en SQLite no puede ir dentro de la transacción de
 * RefreshDatabase. Tampoco usa DatabaseMigrations: su rollback final no puede
 * deshacer la migración de Google en SQLite. La base es en memoria y se
 * descarta sola.
 */
class DemoResetTest extends TestCase
{
    public function test_demo_reset_resiembra_conserva_los_indices_y_el_gitignore(): void
    {
        $this->artisan('migrate')->assertExitCode(0);

        config(['demo.enabled' => true]);
        Storage::fake('local');
        // INDEC y BCRA no responden: tiene que seguir con los índices guardados.
        Http::fake(fn () => Http::response('caído', 500));

        $mes = today()->startOfMonth();
        for ($k = 30; $k >= 0; $k--) {
            $fecha = $mes->copy()->subMonths($k);
            IndexValue::factory()->icl($fecha, round(1000 * 1.025 ** (30 - $k), 4))->create();
            if ($k >= 1) {
                IndexValue::factory()->ipc($fecha, round(5000 * 1.022 ** (30 - $k), 4))->create();
            }
        }
        $indices = IndexValue::query()->count();

        $real = User::factory()->create(['email' => 'real@example.com']);
        Storage::disk('local')->put('.gitignore', "*\n!.gitignore\n");
        Storage::disk('local')->put('gastos/99/factura-real.pdf', 'dato real');

        $this->artisan('demo:reset')->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['email' => $real->email]);
        $this->assertDatabaseHas('users', ['email' => 'demo@pablomandile.com.ar']);
        $this->assertSame($indices, IndexValue::query()->count());

        Storage::disk('local')->assertMissing('gastos/99/factura-real.pdf');
        Storage::disk('local')->assertExists('.gitignore');
    }
}
