<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentosDePropiedadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $duenio;

    private Property $suya;

    private Property $ajena;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->admin()->create();

        $owner = Owner::factory()->conAcceso()->create();
        $this->duenio = $owner->user;

        $this->suya = Property::factory()->create();
        $this->suya->owners()->attach($owner->id, ['porcentaje' => 100]);

        $this->ajena = Property::factory()->create();
        $this->ajena->owners()->attach(Owner::factory()->create()->id, ['porcentaje' => 100]);
    }

    public function test_el_admin_sube_un_documento(): void
    {
        $this->actingAs($this->admin)
            ->post(route('propiedades.documentos.store', $this->suya), [
                'tipo' => 'escritura',
                'nota' => 'Escritura completa, 12 fojas',
                'archivo' => UploadedFile::fake()->create('escritura.pdf', 300, 'application/pdf'),
            ])
            ->assertRedirect();

        $doc = PropertyDocument::query()->sole();

        $this->assertSame($this->suya->id, $doc->property_id);
        $this->assertSame('escritura.pdf', $doc->nombre_original);
        $this->assertSame($this->admin->id, $doc->subido_por);
        $this->assertGreaterThan(0, $doc->tamano);
        Storage::disk('local')->assertExists($doc->path);
    }

    public function test_el_copropietario_sube_a_su_propiedad(): void
    {
        $this->actingAs($this->duenio)
            ->post(route('propiedades.documentos.store', $this->suya), [
                'tipo' => 'plano',
                'archivo' => UploadedFile::fake()->image('plano.png'),
            ])
            ->assertRedirect();

        $this->assertSame(1, $this->suya->documents()->count());
    }

    public function test_el_copropietario_no_sube_a_una_propiedad_ajena(): void
    {
        $this->actingAs($this->duenio)
            ->post(route('propiedades.documentos.store', $this->ajena), [
                'tipo' => 'escritura',
                'archivo' => UploadedFile::fake()->create('escritura.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('property_documents', 0);
        $this->assertTrue(Storage::disk('local')->allFiles() === []);
    }

    public function test_rechaza_un_ejecutable(): void
    {
        $this->actingAs($this->admin)
            ->post(route('propiedades.documentos.store', $this->suya), [
                'tipo' => 'otro',
                'archivo' => UploadedFile::fake()->create('script.exe', 20),
            ])
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('property_documents', 0);
        $this->assertTrue(Storage::disk('local')->allFiles() === []);
    }

    public function test_se_ve_inline_y_se_descarga_con_el_nombre_original(): void
    {
        $doc = $this->subir($this->suya, 'escritura.pdf');

        $inline = $this->actingAs($this->admin)
            ->get(route('propiedades.documentos.show', $doc))
            ->assertOk();
        $this->assertStringStartsWith('inline', (string) $inline->headers->get('content-disposition'));

        $this->actingAs($this->admin)
            ->get(route('propiedades.documentos.show', [$doc, 'descarga' => 1]))
            ->assertOk()
            ->assertDownload('escritura.pdf');
    }

    public function test_el_copropietario_no_descarga_lo_ajeno(): void
    {
        $doc = $this->subir($this->ajena, 'escritura.pdf');

        $this->actingAs($this->duenio)
            ->get(route('propiedades.documentos.show', $doc))
            ->assertForbidden();
    }

    public function test_borrar_saca_el_archivo_y_la_fila(): void
    {
        $doc = $this->subir($this->suya, 'escritura.pdf');
        $path = $doc->path;

        $this->actingAs($this->duenio)
            ->delete(route('propiedades.documentos.destroy', $doc))
            ->assertRedirect();

        $this->assertModelMissing($doc);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_borrar_la_propiedad_borra_sus_archivos(): void
    {
        $doc = $this->subir($this->suya, 'escritura.pdf');
        $path = $doc->path;

        $this->suya->delete();

        $this->assertDatabaseCount('property_documents', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_la_ficha_de_la_propiedad_lista_los_documentos(): void
    {
        $this->subir($this->suya, 'escritura.pdf');

        $this->actingAs($this->admin)
            ->get(route('propiedades.show', $this->suya))
            ->assertInertia(fn ($page) => $page
                ->component('propiedades/Show')
                ->has('propiedad.documentos', 1)
                ->where('propiedad.documentos.0.nombre', 'escritura.pdf')
                ->has('tiposDocumento')
            );
    }

    private function subir(Property $property, string $nombre): PropertyDocument
    {
        $this->actingAs($this->admin)
            ->post(route('propiedades.documentos.store', $property), [
                'tipo' => 'escritura',
                'archivo' => UploadedFile::fake()->create($nombre, 200, 'application/pdf'),
            ])
            ->assertRedirect();

        return $property->documents()->latest('id')->firstOrFail();
    }
}
