<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Owner;
use App\Models\Property;
use App\Models\TenantMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvisoInquilinoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $duenio;

    private Property $suya;

    private Property $ajena;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $owner = Owner::factory()->conAcceso()->create();
        $this->duenio = $owner->user;

        $this->suya = Property::factory()->create();
        $this->suya->owners()->attach($owner->id, ['porcentaje' => 100]);
        Contract::factory()->create(['property_id' => $this->suya->id]);

        $this->ajena = Property::factory()->create();
        $this->ajena->owners()->attach(Owner::factory()->create()->id, ['porcentaje' => 100]);
    }

    public function test_marca_el_aviso_como_enviado(): void
    {
        $this->actingAs($this->duenio)
            ->patch(route('mensaje-inquilino.actualizar', $this->suya), ['estado' => 'enviado'])
            ->assertRedirect();

        $marca = TenantMessage::query()->sole();
        $this->assertSame($this->suya->id, $marca->property_id);
        $this->assertSame(now()->startOfMonth()->toDateString(), $marca->periodo->toDateString());
        $this->assertSame($this->duenio->id, $marca->enviado_por);

        $this->actingAs($this->duenio)
            ->get(route('propiedades.show', $this->suya))
            ->assertInertia(fn ($page) => $page->where('mensajeInquilino.envio.enviado', true));
    }

    public function test_marcar_enviado_dos_veces_no_duplica(): void
    {
        foreach (range(1, 2) as $_) {
            $this->actingAs($this->admin)
                ->patch(route('mensaje-inquilino.actualizar', $this->suya), ['estado' => 'enviado'])
                ->assertRedirect();
        }

        $this->assertDatabaseCount('tenant_messages', 1);
    }

    public function test_vuelve_a_pendiente_con_la_contrasena_correcta(): void
    {
        TenantMessage::factory()->create(['property_id' => $this->suya->id]);

        $this->actingAs($this->duenio)
            ->patch(route('mensaje-inquilino.actualizar', $this->suya), [
                'estado' => 'pendiente',
                'password' => 'password',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tenant_messages', 0);
    }

    public function test_no_vuelve_a_pendiente_sin_contrasena(): void
    {
        TenantMessage::factory()->create(['property_id' => $this->suya->id]);

        $this->actingAs($this->duenio)
            ->patch(route('mensaje-inquilino.actualizar', $this->suya), ['estado' => 'pendiente'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('tenant_messages', 1);
    }

    public function test_no_vuelve_a_pendiente_con_contrasena_incorrecta(): void
    {
        TenantMessage::factory()->create(['property_id' => $this->suya->id]);

        $this->actingAs($this->duenio)
            ->patch(route('mensaje-inquilino.actualizar', $this->suya), [
                'estado' => 'pendiente',
                'password' => 'no-es-mi-clave',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('tenant_messages', 1);
    }

    public function test_no_se_toca_el_aviso_de_una_propiedad_ajena(): void
    {
        $this->actingAs($this->duenio)
            ->patch(route('mensaje-inquilino.actualizar', $this->ajena), ['estado' => 'enviado'])
            ->assertForbidden();

        $this->assertDatabaseCount('tenant_messages', 0);
    }

    public function test_borrar_la_propiedad_borra_la_marca(): void
    {
        TenantMessage::factory()->create(['property_id' => $this->suya->id]);

        $this->suya->contracts()->delete();
        $this->suya->delete();

        $this->assertDatabaseCount('tenant_messages', 0);
    }
}
