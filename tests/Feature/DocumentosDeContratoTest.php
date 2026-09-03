<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentosDeContratoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $duenio;

    private Contract $suyo;

    private Contract $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->admin()->create();

        $owner = Owner::factory()->conAcceso()->create();
        $this->duenio = $owner->user;

        $suya = Property::factory()->create();
        $suya->owners()->attach($owner->id, ['porcentaje' => 100]);
        $this->suyo = Contract::factory()->create(['property_id' => $suya->id]);

        $ajena = Property::factory()->create();
        $ajena->owners()->attach(Owner::factory()->create()->id, ['porcentaje' => 100]);
        $this->ajeno = Contract::factory()->create(['property_id' => $ajena->id]);
    }

    public function test_el_admin_sube_un_documento(): void
    {
        $this->actingAs($this->admin)
            ->post(route('documentos.store', $this->suyo), [
                'tipo' => 'contrato_firmado',
                'nota' => 'Firmado por ambas partes',
                'archivo' => UploadedFile::fake()->create('contrato.pdf', 300, 'application/pdf'),
            ])
            ->assertRedirect();

        $doc = ContractDocument::query()->sole();

        $this->assertSame($this->suyo->id, $doc->contract_id);
        $this->assertSame('contrato.pdf', $doc->nombre_original);
        $this->assertSame($this->admin->id, $doc->subido_por);
        $this->assertGreaterThan(0, $doc->tamano);
        Storage::disk('local')->assertExists($doc->path);
    }

    public function test_el_copropietario_sube_a_su_contrato(): void
    {
        $this->actingAs($this->duenio)
            ->post(route('documentos.store', $this->suyo), [
                'tipo' => 'garantia',
                'archivo' => UploadedFile::fake()->image('garantia.png'),
            ])
            ->assertRedirect();

        $this->assertSame(1, $this->suyo->documents()->count());
    }

    public function test_el_copropietario_no_sube_a_un_contrato_ajeno(): void
    {
        $this->actingAs($this->duenio)
            ->post(route('documentos.store', $this->ajeno), [
                'tipo' => 'contrato_firmado',
                'archivo' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('contract_documents', 0);
        $this->assertTrue(Storage::disk('local')->allFiles() === []);
    }

    public function test_rechaza_un_ejecutable(): void
    {
        $this->actingAs($this->admin)
            ->post(route('documentos.store', $this->suyo), [
                'tipo' => 'otro',
                'archivo' => UploadedFile::fake()->create('script.exe', 20),
            ])
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('contract_documents', 0);
        $this->assertTrue(Storage::disk('local')->allFiles() === []);
    }

    public function test_se_descarga_con_el_nombre_original(): void
    {
        $doc = $this->subir($this->suyo, 'contrato.pdf');

        $this->actingAs($this->admin)
            ->get(route('documentos.show', $doc))
            ->assertOk()
            ->assertDownload('contrato.pdf');
    }

    public function test_el_copropietario_no_descarga_lo_ajeno(): void
    {
        $doc = $this->subir($this->ajeno, 'contrato.pdf');

        $this->actingAs($this->duenio)
            ->get(route('documentos.show', $doc))
            ->assertForbidden();
    }

    public function test_borrar_saca_el_archivo_y_la_fila(): void
    {
        $doc = $this->subir($this->suyo, 'contrato.pdf');
        $path = $doc->path;

        $this->actingAs($this->duenio)
            ->delete(route('documentos.destroy', $doc))
            ->assertRedirect();

        $this->assertModelMissing($doc);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_borrar_el_contrato_borra_sus_archivos(): void
    {
        $doc = $this->subir($this->suyo, 'contrato.pdf');
        $path = $doc->path;

        $this->suyo->delete();

        $this->assertDatabaseCount('contract_documents', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_la_ficha_del_contrato_lista_los_documentos(): void
    {
        $this->subir($this->suyo, 'contrato.pdf');

        $this->actingAs($this->admin)
            ->get(route('contratos.show', $this->suyo))
            ->assertInertia(fn ($page) => $page
                ->component('contratos/Show')
                ->has('contrato.documentos', 1)
                ->where('contrato.documentos.0.nombre', 'contrato.pdf')
                ->has('tiposDocumento')
            );
    }

    private function subir(Contract $contract, string $nombre): ContractDocument
    {
        $this->actingAs($this->admin)
            ->post(route('documentos.store', $contract), [
                'tipo' => 'contrato_firmado',
                'archivo' => UploadedFile::fake()->create($nombre, 200, 'application/pdf'),
            ])
            ->assertRedirect();

        return $contract->documents()->latest('id')->firstOrFail();
    }
}
