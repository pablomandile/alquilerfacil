<?php

namespace Tests\Feature;

use App\Enums\EstadoAjuste;
use App\Enums\EstadoCargo;
use App\Enums\RolUsuario;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\ExpenseDocument;
use App\Models\IndexValue;
use App\Models\Payment;
use App\Models\PropertyDocument;
use App\Models\RentAdjustment;
use App\Models\RentCharge;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La demo se arma con los servicios de la app sobre los índices que ya están en
 * la base (en el server, los reales). Acá van índices inventados relativos a
 * hoy, con el IPC un mes atrasado como el del INDEC.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();

        $mes = today()->startOfMonth();
        for ($k = 30; $k >= 0; $k--) {
            $fecha = $mes->copy()->subMonths($k);
            IndexValue::factory()->icl($fecha, round(1000 * 1.025 ** (30 - $k), 4))->create();

            if ($k >= 1) {
                IndexValue::factory()->ipc($fecha, round(5000 * 1.022 ** (30 - $k), 4))->create();
            }
        }

        $this->seed(DemoSeeder::class);
    }

    private function contrato(string $alias): Contract
    {
        return Contract::query()->whereRelation('property', 'alias', $alias)->sole();
    }

    public function test_la_cuenta_demo_es_un_admin_verificado(): void
    {
        $user = User::query()->where('email', config('demo.email'))->sole();

        $this->assertSame(RolUsuario::Admin, $user->rol);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check(config('demo.password'), $user->password));
    }

    public function test_hay_ajustes_aplicados_y_uno_propuesto(): void
    {
        $this->assertGreaterThan(0, RentAdjustment::query()->where('estado', EstadoAjuste::Aplicado)->count());

        $propuesto = RentAdjustment::query()->where('estado', EstadoAjuste::Propuesto)->sole();
        $this->assertSame('Thames 1450 2°A', $propuesto->contract->property->alias);

        // El contrato nuevo todavía no llegó a su primer ajuste.
        $this->assertSame(0, $this->contrato('Gorriti 4800 PB')->adjustments()->count());

        // El alquiler vigente es el del último ajuste aplicado.
        $cabildo = $this->contrato('Cabildo 2300 4°B');
        $this->assertGreaterThan((float) $cabildo->monto_base, (float) $cabildo->monto_actual);
    }

    public function test_un_cargo_por_contrato_y_mes_hasta_hoy(): void
    {
        $this->assertSame(15, $this->contrato('Cabildo 2300 4°B')->charges()->count());
        $this->assertSame(13, $this->contrato('Thames 1450 2°A')->charges()->count());
        $this->assertSame(5, $this->contrato('Gorriti 4800 PB')->charges()->count());

        $this->assertFalse(RentCharge::query()->where('periodo', '>', today()->startOfMonth())->exists());
    }

    public function test_se_ven_todos_los_estados_de_cobranza(): void
    {
        $mes = today()->startOfMonth();
        $estado = fn (string $alias, $periodo) => $this->contrato($alias)->charges()
            ->whereDate('periodo', $periodo)->sole()->estado;

        $this->assertSame(EstadoCargo::Pagado, $estado('Cabildo 2300 4°B', $mes));
        $this->assertSame(EstadoCargo::Parcial, $estado('Thames 1450 2°A', $mes));
        $this->assertSame(EstadoCargo::Vencido, $estado('Thames 1450 2°A', $mes->copy()->subMonth()));
        $this->assertNotSame(EstadoCargo::Pagado, $estado('Gorriti 4800 PB', $mes));
    }

    public function test_nada_queda_en_el_futuro(): void
    {
        $this->assertFalse(Payment::query()->whereDate('fecha', '>', today())->exists());
        $this->assertFalse(RentAdjustment::query()->where('aplicado_at', '>', now())->exists());
    }

    public function test_cada_archivo_existe_y_es_un_pdf(): void
    {
        $paths = collect()
            ->merge(ExpenseDocument::query()->pluck('path'))
            ->merge(ContractDocument::query()->pluck('path'))
            ->merge(PropertyDocument::query()->pluck('path'))
            ->merge(Payment::query()->whereNotNull('comprobante_path')->pluck('comprobante_path'));

        $this->assertGreaterThan(20, $paths->count());

        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path);
            $this->assertStringStartsWith('%PDF-', (string) Storage::disk('local')->get($path));
        }
    }
}
