<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentosDeGastoTest extends TestCase
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

    public function test_carga_un_gasto_con_factura_y_comprobante(): void
    {
        $this->actingAs($this->admin)
            ->post(route('gastos.store'), [
                ...$this->gastoValido(),
                'factura' => UploadedFile::fake()->create('expensa-09.pdf', 200, 'application/pdf'),
                'comprobante' => UploadedFile::fake()->image('transferencia.jpg'),
            ])
            ->assertRedirect();

        $gasto = Expense::query()->sole();
        $docs = $gasto->documents()->get();

        $this->assertCount(2, $docs);
        $this->assertEqualsCanonicalizing(
            ['factura', 'comprobante'],
            $docs->pluck('tipo.value')->all()
        );
        $this->assertSame('expensa-09.pdf', $docs->firstWhere('tipo.value', 'factura')->nombre_original);
        $this->assertSame($this->admin->id, $docs->first()->subido_por);

        foreach ($docs as $d) {
            Storage::disk('local')->assertExists($d->path);
        }
    }

    public function test_carga_un_gasto_sin_documentos(): void
    {
        $this->actingAs($this->admin)
            ->post(route('gastos.store'), $this->gastoValido())
            ->assertRedirect();

        $this->assertSame(0, Expense::query()->sole()->documents()->count());
    }

    public function test_el_copropietario_carga_documentos_a_su_gasto(): void
    {
        $this->actingAs($this->duenio)
            ->post(route('gastos.store'), [
                ...$this->gastoValido($this->suya),
                'factura' => UploadedFile::fake()->create('luz.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertSame(1, Expense::query()->sole()->documents()->count());
    }

    public function test_agrega_un_documento_al_editar(): void
    {
        $gasto = Expense::factory()->create(['property_id' => $this->suya->id]);

        // Como el front: POST con _method spoofeado (PUT + multipart no anda en PHP).
        $this->actingAs($this->duenio)
            ->post(route('gastos.update', $gasto), [
                ...$this->gastoValido($this->suya),
                '_method' => 'PUT',
                'comprobante' => UploadedFile::fake()->create('recibo.pdf', 80, 'application/pdf'),
            ])
            ->assertRedirect();

        $doc = $gasto->documents()->sole();
        $this->assertSame('comprobante', $doc->tipo->value);
        Storage::disk('local')->assertExists($doc->path);
    }

    public function test_rechaza_un_ejecutable_y_no_crea_el_gasto(): void
    {
        $this->actingAs($this->admin)
            ->post(route('gastos.store'), [
                ...$this->gastoValido(),
                'factura' => UploadedFile::fake()->create('virus.exe', 10),
            ])
            ->assertSessionHasErrors('factura');

        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('expense_documents', 0);
    }

    public function test_ve_inline_y_descarga_pero_no_el_ajeno(): void
    {
        $propio = $this->subirDoc($this->suya, 'expensa.pdf');
        $ajeno = $this->subirDoc($this->ajena, 'otra.pdf');

        $inline = $this->actingAs($this->duenio)
            ->get(route('gastos.documentos.show', $propio))
            ->assertOk();
        $this->assertStringStartsWith('inline', (string) $inline->headers->get('content-disposition'));

        $this->actingAs($this->duenio)
            ->get(route('gastos.documentos.show', [$propio, 'descarga' => 1]))
            ->assertOk()
            ->assertDownload('expensa.pdf');

        $this->actingAs($this->duenio)
            ->get(route('gastos.documentos.show', $ajeno))
            ->assertForbidden();
    }

    public function test_borra_un_documento_pero_no_el_ajeno(): void
    {
        $ajeno = $this->subirDoc($this->ajena, 'otra.pdf');
        $this->actingAs($this->duenio)
            ->delete(route('gastos.documentos.destroy', $ajeno))
            ->assertForbidden();

        $propio = $this->subirDoc($this->suya, 'expensa.pdf');
        $path = $propio->path;

        $this->actingAs($this->duenio)
            ->delete(route('gastos.documentos.destroy', $propio))
            ->assertRedirect();

        $this->assertModelMissing($propio);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_borrar_el_gasto_borra_sus_documentos_y_archivos(): void
    {
        $doc = $this->subirDoc($this->suya, 'expensa.pdf');
        $path = $doc->path;
        $gasto = $doc->expense;

        $this->actingAs($this->duenio)
            ->delete(route('gastos.destroy', $gasto))
            ->assertRedirect();

        $this->assertDatabaseCount('expense_documents', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_el_form_de_edicion_y_la_lista_muestran_los_documentos(): void
    {
        $doc = $this->subirDoc($this->suya, 'expensa.pdf');

        $this->actingAs($this->admin)
            ->get(route('gastos.edit', $doc->expense))
            ->assertInertia(fn ($page) => $page
                ->component('gastos/Form')
                ->has('gasto.documentos', 1)
                ->where('gasto.documentos.0.nombre', 'expensa.pdf')
            );

        $this->actingAs($this->admin)
            ->get(route('gastos.index'))
            ->assertInertia(fn ($page) => $page
                ->component('gastos/Index')
                ->has('gastos.0.documentos', 1)
            );
    }

    private function subirDoc(Property $property, string $nombre): ExpenseDocument
    {
        $this->actingAs($this->admin)
            ->post(route('gastos.store'), [
                ...$this->gastoValido($property),
                'factura' => UploadedFile::fake()->create($nombre, 150, 'application/pdf'),
            ])
            ->assertRedirect();

        return Expense::query()->latest('id')->firstOrFail()->documents()->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function gastoValido(?Property $property = null): array
    {
        return [
            'property_id' => ($property ?? $this->suya)->id,
            'tipo' => 'expensas',
            'categoria' => 'expensas',
            'descripcion' => 'Expensas de prueba',
            'periodo' => today()->toDateString(),
            'monto' => 50000,
            'vencimiento' => today()->addDays(10)->toDateString(),
            'a_cargo_de' => 'inquilino',
            'pagado' => false,
        ];
    }
}
