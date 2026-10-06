<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ComprobanteDePagoTest extends TestCase
{
    use RefreshDatabase;

    private User $duenio;

    private RentCharge $suyo;

    private RentCharge $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $owner = Owner::factory()->conAcceso()->create();
        $this->duenio = $owner->user;

        $suya = Property::factory()->create();
        $suya->owners()->attach($owner->id, ['porcentaje' => 100]);

        $ajena = Property::factory()->create();
        $ajena->owners()->attach(Owner::factory()->create()->id, ['porcentaje' => 100]);

        $this->suyo = RentCharge::factory()->create([
            'contract_id' => Contract::factory()->create(['property_id' => $suya->id])->id,
        ]);
        $this->ajeno = RentCharge::factory()->create([
            'contract_id' => Contract::factory()->create(['property_id' => $ajena->id])->id,
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function registrarPago(RentCharge $cargo, array $extra = []): TestResponse
    {
        return $this->actingAs($this->duenio)->post(route('pagos.store', $cargo), [
            'fecha' => today()->toDateString(),
            'monto' => $cargo->monto,
            'medio' => 'transferencia',
            ...$extra,
        ]);
    }

    public function test_registra_el_pago_con_su_comprobante(): void
    {
        $this->registrarPago($this->suyo, [
            'comprobante' => UploadedFile::fake()->image('transferencia.jpg'),
        ])->assertRedirect();

        $pago = Payment::query()->sole();

        $this->assertSame('transferencia.jpg', $pago->comprobante_nombre);
        $this->assertSame('image/jpeg', $pago->comprobante_mime);
        Storage::disk('local')->assertExists((string) $pago->comprobante_path);
    }

    public function test_el_comprobante_es_opcional(): void
    {
        $this->registrarPago($this->suyo)->assertRedirect();

        $this->assertNull(Payment::query()->sole()->comprobante_path);
    }

    public function test_rechaza_un_ejecutable_y_no_registra_el_pago(): void
    {
        $this->registrarPago($this->suyo, [
            'comprobante' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertSessionHasErrors('comprobante');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_ve_el_comprobante_propio_pero_no_el_ajeno(): void
    {
        $this->registrarPago($this->suyo, [
            'comprobante' => UploadedFile::fake()->create('recibo.pdf', 50, 'application/pdf'),
        ]);
        $propio = Payment::query()->sole();

        $ajeno = Payment::factory()->create([
            'rent_charge_id' => $this->ajeno->id,
            'comprobante_path' => 'pagos/x/otro.pdf',
            'comprobante_nombre' => 'otro.pdf',
            'comprobante_mime' => 'application/pdf',
        ]);

        $this->actingAs($this->duenio)
            ->get(route('pagos.comprobante', [$propio, 'descarga' => 1]))
            ->assertOk()
            ->assertDownload('recibo.pdf');

        $this->actingAs($this->duenio)
            ->get(route('pagos.comprobante', $ajeno))
            ->assertForbidden();
    }

    public function test_un_pago_sin_comprobante_da_404(): void
    {
        $this->registrarPago($this->suyo);

        $this->actingAs($this->duenio)
            ->get(route('pagos.comprobante', Payment::query()->sole()))
            ->assertNotFound();
    }

    public function test_borrar_el_pago_borra_el_archivo(): void
    {
        $this->registrarPago($this->suyo, [
            'comprobante' => UploadedFile::fake()->image('transferencia.png'),
        ]);
        $pago = Payment::query()->sole();
        $path = (string) $pago->comprobante_path;

        $this->actingAs($this->duenio)
            ->delete(route('pagos.destroy', $pago))
            ->assertRedirect();

        Storage::disk('local')->assertMissing($path);
    }
}
