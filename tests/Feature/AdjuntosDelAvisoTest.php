<?php

namespace Tests\Feature;

use App\Enums\ACargoDe;
use App\Enums\TipoDocumentoGasto;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Las facturas y comprobantes de los gastos del aviso viajan como enlaces
 * firmados: el inquilino los abre sin cuenta, pero sólo con el enlace exacto.
 */
class AdjuntosDelAvisoTest extends TestCase
{
    use RefreshDatabase;

    private function gastoConFactura(Property $propiedad): ExpenseDocument
    {
        $gasto = Expense::factory()->create([
            'property_id' => $propiedad->id,
            'a_cargo_de' => ACargoDe::Inquilino,
            'descripcion' => 'Expensas',
            'vencimiento' => today()->startOfMonth()->addDays(14),
        ]);

        Storage::disk('local')->put('gastos/factura.pdf', 'contenido');

        return ExpenseDocument::factory()->create([
            'expense_id' => $gasto->id,
            'tipo' => TipoDocumentoGasto::Factura,
            'nombre_original' => 'expensas.pdf',
            'path' => 'gastos/factura.pdf',
        ]);
    }

    public function test_el_aviso_trae_los_enlaces_de_los_documentos_del_gasto(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $propiedad = Property::factory()->create();
        Contract::factory()->create(['property_id' => $propiedad->id]);
        $this->gastoConFactura($propiedad);

        $this->actingAs($admin)->get(route('propiedades.show', $propiedad))
            ->assertInertia(fn ($page) => $page
                ->has('mensajeInquilino.gastos.0.adjuntos', 1)
                ->where('mensajeInquilino.gastos.0.adjuntos.0.nombre', 'Expensas — factura / expensa')
                ->where('mensajeInquilino.gastos.0.adjuntos.0.url', fn ($url) => str_contains($url, 'signature=')));
    }

    public function test_el_enlace_firmado_abre_el_archivo_sin_sesion(): void
    {
        Storage::fake('local');
        $documento = $this->gastoConFactura(Property::factory()->create());

        $this->get($documento->enlaceCompartido())->assertOk();
    }

    public function test_sin_firma_o_con_firma_alterada_no_abre(): void
    {
        Storage::fake('local');
        $documento = $this->gastoConFactura(Property::factory()->create());

        $this->get(route('gastos.documentos.compartido', $documento))->assertForbidden();
        $this->get($documento->enlaceCompartido().'x')->assertForbidden();
    }

    public function test_el_enlace_vencido_no_abre(): void
    {
        Storage::fake('local');
        $documento = $this->gastoConFactura(Property::factory()->create());
        $enlace = $documento->enlaceCompartido();

        $this->travel(31)->days();

        $this->get($enlace)->assertForbidden();
    }
}
