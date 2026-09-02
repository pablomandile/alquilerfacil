<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
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

    public function test_el_dueno_no_puede_crear_ni_editar_nada(): void
    {
        $rutas = [
            ['get', route('propiedades.create')],
            ['post', route('propiedades.store')],
            ['get', route('propiedades.edit', $this->suya)],
            ['put', route('propiedades.update', $this->suya)],
            ['delete', route('propiedades.destroy', $this->suya)],
            ['get', route('contratos.create')],
            ['get', route('gastos.create')],
            ['get', route('inquilinos.create')],
            ['get', route('propietarios.create')],
            ['post', route('indices.sincronizar')],
            ['post', route('cobranzas.generar')],
            ['post', route('ajustes.recalcular')],
        ];

        foreach ($rutas as [$metodo, $url]) {
            $this->actingAs($this->duenio)
                ->{$metodo}($url)
                ->assertForbidden("Se esperaba 403 en {$metodo} {$url}");
        }
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

        // Los índices son sólo para el admin.
        $this->actingAs($this->admin)->get(route('indices.index'))->assertOk();
    }

    public function test_un_visitante_sin_sesion_va_al_login(): void
    {
        $this->get(route('propiedades.index'))->assertRedirect(route('login'));
    }
}
