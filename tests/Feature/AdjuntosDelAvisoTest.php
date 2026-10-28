<?php

namespace Tests\Feature;

use App\Enums\ACargoDe;
use App\Enums\CategoriaGasto;
use App\Enums\TipoDocumentoGasto;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Las facturas y comprobantes de los gastos del aviso viajan como enlaces
 * firmados: el inquilino los abre sin cuenta, pero sólo con el enlace exacto.
 */
class AdjuntosDelAvisoTest extends TestCase
{
    use RefreshDatabase;

    private function gastoConFactura(
        Property $propiedad,
        CategoriaGasto $categoria = CategoriaGasto::Expensas,
        TipoDocumentoGasto $tipoDocumento = TipoDocumentoGasto::Factura,
    ): ExpenseDocument {
        $gasto = Expense::factory()->create([
            'property_id' => $propiedad->id,
            'a_cargo_de' => ACargoDe::Inquilino,
            'categoria' => $categoria,
            'tipo' => $categoria->tipoPorDefecto(),
            'descripcion' => $categoria->label(),
            'vencimiento' => today()->startOfMonth()->addDays(14),
        ]);

        Storage::disk('local')->put('gastos/factura.pdf', 'contenido');

        return ExpenseDocument::factory()->create([
            'expense_id' => $gasto->id,
            'tipo' => $tipoDocumento,
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
                ->where('mensajeInquilino.gastos.0.adjuntos.0.nombre', 'Expensas — expensa')
                ->where('mensajeInquilino.gastos.0.adjuntos.0.url', fn ($url) => str_contains($url, 'signature=')));
    }

    public function test_el_adjunto_se_llama_factura_expensa_o_comprobante_segun_el_gasto(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $propiedad = Property::factory()->create();
        Contract::factory()->create(['property_id' => $propiedad->id]);
        $this->gastoConFactura($propiedad, CategoriaGasto::Luz);
        $this->gastoConFactura($propiedad, CategoriaGasto::Agua, TipoDocumentoGasto::Comprobante);

        $this->actingAs($admin)->get(route('propiedades.show', $propiedad))
            ->assertInertia(fn ($page) => $page
                ->where('mensajeInquilino.gastos', fn ($gastos) => collect($gastos)
                    ->pluck('adjuntos.0.nombre')->sort()->values()->all()
                    === ['Agua — comprobante de pago', 'Luz — factura']));
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

        // La firma dura 60 días.
        $this->travel(59)->days();
        $this->get($enlace)->assertOk();

        $this->travel(2)->days();
        $this->get($enlace)->assertForbidden();
    }

    private function conZep(): void
    {
        config(['services.zep.token' => 'token-de-prueba']);
    }

    public function test_acorta_el_enlace_con_zep_y_lo_guarda(): void
    {
        // El vencimiento se compara al segundo: sin congelar el reloj, el test
        // falla si el segundo cambia entre que se acorta y que se verifica.
        $this->freezeTime();
        Storage::fake('local');
        $this->conZep();
        Http::fake(['zep.com.ar/*' => Http::response(['data' => ['short_url' => 'https://zep.com.ar/aB3']], 201)]);
        $documento = $this->gastoConFactura(Property::factory()->create());

        $this->assertSame('https://zep.com.ar/aB3', $documento->enlaceCompartido());

        Http::assertSent(fn (Request $r) => $r->url() === 'https://zep.com.ar/api/v1/links'
            && $r->hasHeader('Authorization', 'Bearer token-de-prueba')
            && str_contains($r['url'], 'signature=')
            // El link de Zep vence junto con la firma.
            && $r['expires_at'] === now()->addDays(60)->format(DATE_ATOM));

        $documento->refresh();
        $this->assertSame('https://zep.com.ar/aB3', $documento->enlace_corto);
    }

    public function test_reusa_el_enlace_corto_sin_volver_a_llamar_a_zep(): void
    {
        Storage::fake('local');
        $this->conZep();
        Http::fake(['zep.com.ar/*' => Http::response(['data' => ['short_url' => 'https://zep.com.ar/aB3']], 201)]);
        $documento = $this->gastoConFactura(Property::factory()->create());

        $documento->enlaceCompartido();
        $this->travel(29)->days();
        $this->assertSame('https://zep.com.ar/aB3', $documento->fresh()?->enlaceCompartido());

        Http::assertSentCount(1);
    }

    public function test_pide_uno_nuevo_cuando_al_guardado_le_queda_menos_de_un_mes(): void
    {
        Storage::fake('local');
        $this->conZep();
        Http::fake(['zep.com.ar/*' => Http::sequence()
            ->push(['data' => ['short_url' => 'https://zep.com.ar/aB3']], 201)
            ->push(['data' => ['short_url' => 'https://zep.com.ar/cD4']], 201)]);
        $documento = $this->gastoConFactura(Property::factory()->create());

        $documento->enlaceCompartido();
        $this->travel(31)->days();

        $this->assertSame('https://zep.com.ar/cD4', $documento->fresh()?->enlaceCompartido());
        Http::assertSentCount(2);
    }

    public function test_si_zep_falla_va_el_enlace_largo_y_no_se_guarda(): void
    {
        Storage::fake('local');
        $this->conZep();
        Http::fake(['zep.com.ar/*' => Http::response(['message' => 'Error'], 500)]);
        $documento = $this->gastoConFactura(Property::factory()->create());

        $enlace = $documento->enlaceCompartido();

        $this->assertStringContainsString('signature=', $enlace);
        $this->get($enlace)->assertOk();
        $this->assertNull($documento->fresh()?->enlace_corto);
    }

    public function test_sin_token_no_llama_a_zep(): void
    {
        Storage::fake('local');
        Http::fake();
        $documento = $this->gastoConFactura(Property::factory()->create());

        $this->assertStringContainsString('signature=', $documento->enlaceCompartido());
        Http::assertNothingSent();
    }
}
