<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesoDeDuenoTest extends TestCase
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

        $this->suya = Property::factory()->create(['alias' => 'La suya']);
        $this->suya->owners()->attach($owner->id, ['porcentaje' => 100]);

        $this->ajena = Property::factory()->create(['alias' => 'La ajena']);
        $this->ajena->owners()->attach(
            Owner::factory()->create()->id,
            ['porcentaje' => 100]
        );
    }

    // ── Lectura ──────────────────────────────────────────────────────────

    public function test_el_dueno_ve_solo_sus_propiedades_en_el_listado(): void
    {
        $this->actingAs($this->duenio)
            ->get(route('propiedades.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('propiedades/Index')
                ->has('propiedades', 1)
                ->where('propiedades.0.alias', 'La suya')
            );
    }

    public function test_el_admin_ve_todas_las_propiedades(): void
    {
        $this->actingAs($this->admin)
            ->get(route('propiedades.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('propiedades', 2));
    }

    /**
     * 404 y no 403: así el propietario no puede confirmar que la propiedad
     * ajena exista probando ids.
     */
    public function test_pedir_una_propiedad_ajena_da_404(): void
    {
        $this->actingAs($this->duenio)
            ->get(route('propiedades.show', $this->ajena))
            ->assertNotFound();

        $this->actingAs($this->duenio)
            ->get(route('propiedades.show', $this->suya))
            ->assertOk();
    }

    public function test_el_dueno_solo_ve_los_contratos_y_gastos_de_lo_suyo(): void
    {
        Contract::factory()->create(['property_id' => $this->suya->id]);
        Contract::factory()->create(['property_id' => $this->ajena->id]);
        Expense::factory()->create(['property_id' => $this->suya->id]);
        Expense::factory()->create(['property_id' => $this->ajena->id]);

        $this->actingAs($this->duenio)
            ->get(route('contratos.index'))
            ->assertInertia(fn ($page) => $page->has('contratos', 1));

        $this->actingAs($this->duenio)
            ->get(route('gastos.index'))
            ->assertInertia(fn ($page) => $page->has('gastos', 1));
    }

    /** Un dueño no tiene por qué ver el CBU ni los datos de los otros dueños. */
    public function test_el_dueno_se_ve_solo_a_si_mismo_en_propietarios(): void
    {
        $this->actingAs($this->duenio)
            ->get(route('propietarios.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('propietarios', 1));

        $this->actingAs($this->admin)
            ->get(route('propietarios.index'))
            ->assertInertia(fn ($page) => $page->has('propietarios', 2));
    }

    /** Todas las pantallas de lectura tienen que abrir para ambos roles. */
    public function test_las_pantallas_de_lectura_abren_para_los_dos_roles(): void
    {
        $pantallas = [
            'dashboard', 'propiedades.index', 'contratos.index', 'ajustes.index',
            'cobranzas.index', 'gastos.index', 'liquidaciones.index',
            'propietarios.index', 'inquilinos.index',
        ];

        foreach ([$this->admin, $this->duenio] as $usuario) {
            foreach ($pantallas as $ruta) {
                $this->actingAs($usuario)
                    ->get(route($ruta))
                    ->assertOk("Falló {$ruta} para {$usuario->rol->value}");
            }
        }

        $this->actingAs($this->admin)->get(route('indices.index'))->assertOk();
    }

    public function test_un_visitante_sin_sesion_va_al_login(): void
    {
        $this->get(route('propiedades.index'))->assertRedirect(route('login'));
    }

    // ── Escritura ────────────────────────────────────────────────────────

    /** La estructura (alta/baja de propiedades, dueños, índices) es del admin. */
    public function test_el_copropietario_no_toca_la_estructura(): void
    {
        $rutas = [
            ['get', route('propiedades.create')],
            ['post', route('propiedades.store')],
            ['get', route('propiedades.edit', $this->suya)],
            ['put', route('propiedades.update', $this->suya)],
            ['delete', route('propiedades.destroy', $this->suya)],
            ['get', route('propietarios.create')],
            ['post', route('indices.sincronizar')],
            ['post', route('ajustes.recalcular')],
        ];

        foreach ($rutas as [$metodo, $url]) {
            $this->actingAs($this->duenio)
                ->{$metodo}($url)
                ->assertForbidden("Se esperaba 403 en {$metodo} {$url}");
        }
    }

    /** Sobre su propiedad, el copropietario gestiona como el admin. */
    public function test_el_copropietario_gestiona_lo_de_su_propiedad(): void
    {
        $this->actingAs($this->duenio);

        $this->get(route('gastos.create'))->assertOk();
        $this->get(route('contratos.create'))->assertOk();
        $this->get(route('inquilinos.create'))->assertOk();
        $this->post(route('cobranzas.generar'))->assertRedirect();

        $gasto = Expense::factory()->create(['property_id' => $this->suya->id]);
        $this->get(route('gastos.index'))->assertOk();
        $this->delete(route('gastos.destroy', $gasto))->assertRedirect();
        $this->assertModelMissing($gasto);
    }

    public function test_el_copropietario_no_toca_lo_ajeno(): void
    {
        $gastoAjeno = Expense::factory()->create(['property_id' => $this->ajena->id]);
        $contratoAjeno = Contract::factory()->create(['property_id' => $this->ajena->id]);
        $cargoAjeno = RentCharge::factory()->create(['contract_id' => $contratoAjeno->id]);

        $this->actingAs($this->duenio);

        // Cargar un gasto apuntando a la propiedad ajena.
        $this->post(route('gastos.store'), $this->gastoValido($this->ajena))
            ->assertForbidden();

        // Editar / borrar registros de la propiedad ajena (ruta model-bound).
        $this->put(route('gastos.update', $gastoAjeno), $this->gastoValido($this->ajena))
            ->assertForbidden();
        $this->delete(route('gastos.destroy', $gastoAjeno))->assertForbidden();
        $this->get(route('contratos.edit', $contratoAjeno))->assertForbidden();
        $this->post(route('pagos.store', $cargoAjeno), [])->assertForbidden();
        $this->delete(route('cobranzas.destroy', $cargoAjeno))->assertForbidden();
    }

    public function test_el_copropietario_borra_un_cargo_sin_pagos(): void
    {
        $contrato = Contract::factory()->create(['property_id' => $this->suya->id]);
        $cargo = RentCharge::factory()->create(['contract_id' => $contrato->id]);

        $this->actingAs($this->duenio)
            ->delete(route('cobranzas.destroy', $cargo))
            ->assertRedirect();

        $this->assertModelMissing($cargo);
    }

    public function test_no_se_puede_borrar_un_cargo_con_pagos_registrados(): void
    {
        $contrato = Contract::factory()->create(['property_id' => $this->suya->id]);
        $cargo = RentCharge::factory()->create(['contract_id' => $contrato->id]);
        $cargo->payments()->create([
            'fecha' => today(),
            'monto' => 1000,
            'medio' => 'transferencia',
        ]);

        $this->actingAs($this->duenio)
            ->delete(route('cobranzas.destroy', $cargo))
            ->assertRedirect();

        $this->assertModelExists($cargo);
    }

    /** Borrar un cargo generado (con reparto entre dueños) no deja repartos huérfanos. */
    public function test_borrar_un_cargo_borra_tambien_su_reparto(): void
    {
        Contract::factory()->create(['property_id' => $this->suya->id]);

        $this->actingAs($this->admin)->post(route('cobranzas.generar'));

        $cargo = RentCharge::query()->sole();
        $this->assertSame(1, $cargo->shares()->count());

        $this->actingAs($this->admin)
            ->delete(route('cobranzas.destroy', $cargo))
            ->assertRedirect();

        $this->assertDatabaseCount('owner_shares', 0);
    }

    public function test_el_copropietario_carga_un_gasto_extraordinario_y_se_reparte(): void
    {
        $this->actingAs($this->duenio)
            ->post(route('gastos.store'), $this->gastoValido($this->suya, extraordinario: true))
            ->assertRedirect(route('gastos.index'));

        $gasto = Expense::query()->where('property_id', $this->suya->id)->sole();

        // Un solo dueño al 100 % → su parte es el total.
        $this->assertSame(1, $gasto->shares()->count());
        $this->assertSame((string) $gasto->monto, (string) $gasto->shares()->sole()->monto);
    }

    // ── Vínculo de la cuenta ─────────────────────────────────────────────

    public function test_guardar_un_owner_con_email_de_una_cuenta_lo_vincula(): void
    {
        $user = User::factory()->create(['email' => 'hermana@gmail.com']);

        $owner = Owner::factory()->create(['email' => 'hermana@gmail.com']);

        $this->assertSame($user->id, $owner->fresh()->user_id);
        $this->assertTrue($owner->tieneAcceso());
    }

    public function test_al_ingresar_se_vincula_la_ficha_de_propietario_por_email(): void
    {
        $owner = Owner::factory()->create([
            'email' => 'hermana@gmail.com',
            'user_id' => null,
        ]);

        $propiedad = Property::factory()->create(['alias' => 'De la hermana']);
        $propiedad->owners()->attach($owner->id, ['porcentaje' => 100]);

        $user = User::factory()->create(['email' => 'hermana@gmail.com']);
        $this->assertNull($owner->fresh()->user_id);

        event(new Login('web', $user, false));

        $this->assertSame($user->id, $owner->fresh()->user_id);

        $this->actingAs($user)
            ->get(route('propiedades.index'))
            ->assertInertia(fn ($page) => $page
                ->has('propiedades', 1)
                ->where('propiedades.0.alias', 'De la hermana')
            );
    }

    /** @return array<string, mixed> */
    private function gastoValido(Property $property, bool $extraordinario = false): array
    {
        return [
            'property_id' => $property->id,
            'tipo' => $extraordinario ? 'extraordinario' : 'servicio',
            'categoria' => $extraordinario ? 'reparacion' : 'luz',
            'descripcion' => 'Gasto de prueba',
            'periodo' => today()->toDateString(),
            'monto' => 50000,
            'vencimiento' => today()->addDays(10)->toDateString(),
            'a_cargo_de' => $extraordinario ? 'propietarios' : 'inquilino',
            'pagado' => false,
        ];
    }
}
