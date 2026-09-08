<?php

namespace Tests\Feature;

use App\Models\AdminThread;
use App\Models\AdminThreadAttachment;
use App\Models\AdminThreadEntry;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeguimientoAdministracionTest extends TestCase
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

    public function test_el_admin_abre_un_tema_con_su_primera_entrada(): void
    {
        $this->actingAs($this->admin)
            ->post(route('administracion.temas.store', $this->suya), [
                'titulo' => 'Filtración en el baño',
                'categoria' => 'reclamo',
                'fecha' => '2026-09-01',
                'detalle' => 'Avisé a la administración por mail.',
                'archivos' => [
                    UploadedFile::fake()->create('mail.pdf', 120, 'application/pdf'),
                    UploadedFile::fake()->image('foto.jpg'),
                ],
            ])
            ->assertRedirect();

        $tema = AdminThread::query()->sole();

        $this->assertSame($this->suya->id, $tema->property_id);
        $this->assertSame('Filtración en el baño', $tema->titulo);
        $this->assertSame('abierto', $tema->estado->value);

        $entrada = $tema->entries()->sole();
        $this->assertSame('2026-09-01', $entrada->fecha->toDateString());
        $this->assertSame($this->admin->id, $entrada->registrado_por);
        $this->assertCount(2, $entrada->attachments);

        foreach ($entrada->attachments as $adjunto) {
            Storage::disk('local')->assertExists($adjunto->path);
        }
    }

    public function test_el_copropietario_abre_un_tema_en_su_propiedad(): void
    {
        $this->actingAs($this->duenio)
            ->post(route('administracion.temas.store', $this->suya), $this->temaValido())
            ->assertRedirect();

        $this->assertSame(1, $this->suya->adminThreads()->count());
    }

    public function test_el_copropietario_no_abre_un_tema_en_propiedad_ajena(): void
    {
        $this->actingAs($this->duenio)
            ->post(route('administracion.temas.store', $this->ajena), $this->temaValido())
            ->assertForbidden();

        $this->assertDatabaseCount('admin_threads', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_agrega_una_entrada_con_adjunto_a_un_tema(): void
    {
        $tema = $this->crearTema($this->suya);

        $this->actingAs($this->duenio)
            ->post(route('administracion.entradas.store', $tema), [
                'fecha' => '2026-09-15',
                'detalle' => 'Respondieron que mandan un plomero.',
                'archivos' => [UploadedFile::fake()->create('respuesta.pdf', 90, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->assertSame(2, $tema->entries()->count());

        $ultima = $this->ultimaEntrada($tema);
        $this->assertCount(1, $ultima->attachments);
        Storage::disk('local')->assertExists($ultima->attachments->first()->path);
    }

    public function test_marca_un_tema_como_resuelto_y_lo_reabre(): void
    {
        $tema = $this->crearTema($this->suya);

        $this->actingAs($this->duenio)
            ->patch(route('administracion.temas.update', $tema), ['estado' => 'resuelto'])
            ->assertRedirect();

        $tema->refresh();
        $this->assertSame('resuelto', $tema->estado->value);
        $this->assertNotNull($tema->resuelto_at);

        $this->actingAs($this->duenio)
            ->patch(route('administracion.temas.update', $tema), ['estado' => 'abierto'])
            ->assertRedirect();

        $tema->refresh();
        $this->assertSame('abierto', $tema->estado->value);
        $this->assertNull($tema->resuelto_at);
    }

    public function test_ve_y_descarga_un_adjunto_pero_no_el_ajeno(): void
    {
        $propio = $this->crearTemaConAdjunto($this->suya, 'carta.pdf');
        $ajeno = $this->crearTemaConAdjunto($this->ajena, 'otra.pdf');

        // Por defecto inline (visor); ?descarga=1 fuerza la bajada.
        $inline = $this->actingAs($this->duenio)
            ->get(route('administracion.adjuntos.show', $propio))
            ->assertOk();
        $this->assertStringStartsWith('inline', (string) $inline->headers->get('content-disposition'));

        $this->actingAs($this->duenio)
            ->get(route('administracion.adjuntos.show', [$propio, 'descarga' => 1]))
            ->assertOk()
            ->assertDownload('carta.pdf');

        $this->actingAs($this->duenio)
            ->get(route('administracion.adjuntos.show', $ajeno))
            ->assertForbidden();
    }

    public function test_borrar_una_entrada_intermedia_borra_sus_adjuntos(): void
    {
        $tema = $this->crearTema($this->suya);
        $this->actingAs($this->admin)->post(route('administracion.entradas.store', $tema), [
            'fecha' => '2026-09-10',
            'detalle' => 'Segunda entrada.',
            'archivos' => [UploadedFile::fake()->create('x.pdf', 50, 'application/pdf')],
        ]);

        $entrada = $this->ultimaEntrada($tema);
        $path = $entrada->attachments->first()->path;

        $this->actingAs($this->duenio)
            ->delete(route('administracion.entradas.destroy', $entrada))
            ->assertRedirect();

        $this->assertModelMissing($entrada);
        Storage::disk('local')->assertMissing($path);
        $this->assertTrue($tema->fresh()->exists);
    }

    public function test_borrar_la_ultima_entrada_borra_el_tema(): void
    {
        $tema = $this->crearTema($this->suya);
        $entrada = $tema->entries()->sole();

        $this->actingAs($this->duenio)
            ->delete(route('administracion.entradas.destroy', $entrada))
            ->assertRedirect();

        $this->assertModelMissing($tema);
        $this->assertModelMissing($entrada);
    }

    public function test_borrar_el_tema_se_lleva_entradas_y_archivos(): void
    {
        $tema = $this->crearTemaConAdjunto($this->suya, 'carta.pdf')->entry->thread;
        $path = $tema->entries()->first()->attachments->first()->path;

        $this->actingAs($this->admin)
            ->delete(route('administracion.temas.destroy', $tema))
            ->assertRedirect();

        $this->assertDatabaseCount('admin_threads', 0);
        $this->assertDatabaseCount('admin_thread_entries', 0);
        $this->assertDatabaseCount('admin_thread_attachments', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_borrar_la_propiedad_se_lleva_los_temas_y_archivos(): void
    {
        $adjunto = $this->crearTemaConAdjunto($this->suya, 'carta.pdf');
        $path = $adjunto->path;

        $this->suya->delete();

        $this->assertDatabaseCount('admin_threads', 0);
        $this->assertDatabaseCount('admin_thread_attachments', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_el_copropietario_no_toca_temas_ajenos(): void
    {
        $ajeno = $this->crearTema($this->ajena);
        $entrada = $ajeno->entries()->sole();

        $this->actingAs($this->duenio);

        $this->patch(route('administracion.temas.update', $ajeno), ['estado' => 'resuelto'])
            ->assertForbidden();
        $this->delete(route('administracion.temas.destroy', $ajeno))->assertForbidden();
        $this->post(route('administracion.entradas.store', $ajeno), [
            'fecha' => '2026-09-10', 'detalle' => 'Intruso.',
        ])->assertForbidden();
        $this->delete(route('administracion.entradas.destroy', $entrada))->assertForbidden();
    }

    public function test_rechaza_un_adjunto_ejecutable(): void
    {
        $this->actingAs($this->admin)
            ->post(route('administracion.temas.store', $this->suya), [
                ...$this->temaValido(),
                'archivos' => [UploadedFile::fake()->create('virus.exe', 10)],
            ])
            ->assertSessionHasErrors('archivos.0');

        $this->assertDatabaseCount('admin_threads', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_la_ficha_de_la_propiedad_lista_el_seguimiento(): void
    {
        $this->crearTemaConAdjunto($this->suya, 'carta.pdf');

        $this->actingAs($this->admin)
            ->get(route('propiedades.show', $this->suya))
            ->assertInertia(fn ($page) => $page
                ->component('propiedades/Show')
                ->has('propiedad.administracion', 1)
                ->has('propiedad.administracion.0.entradas', 1)
                ->has('propiedad.administracion.0.entradas.0.adjuntos', 1)
                ->has('categoriasTemaAdmin')
            );
    }

    /** @return array<string, mixed> */
    private function temaValido(): array
    {
        return [
            'titulo' => 'Consulta por expensas',
            'categoria' => 'consulta',
            'fecha' => '2026-09-01',
            'detalle' => 'Pregunté por el detalle de la última liquidación.',
        ];
    }

    private function ultimaEntrada(AdminThread $tema): AdminThreadEntry
    {
        return AdminThreadEntry::query()
            ->where('admin_thread_id', $tema->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function crearTema(Property $property): AdminThread
    {
        $this->actingAs($this->admin)
            ->post(route('administracion.temas.store', $property), $this->temaValido())
            ->assertRedirect();

        return $property->adminThreads()->latest('id')->firstOrFail();
    }

    private function crearTemaConAdjunto(Property $property, string $nombre): AdminThreadAttachment
    {
        $this->actingAs($this->admin)
            ->post(route('administracion.temas.store', $property), [
                ...$this->temaValido(),
                'archivos' => [UploadedFile::fake()->create($nombre, 100, 'application/pdf')],
            ])
            ->assertRedirect();

        return $property->adminThreads()->latest('id')->firstOrFail()
            ->entries()->first()->attachments()->firstOrFail();
    }
}
