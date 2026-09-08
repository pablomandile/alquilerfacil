<?php

namespace Database\Seeders;

use App\Enums\ACargoDe;
use App\Enums\CategoriaGasto;
use App\Enums\CategoriaTemaAdmin;
use App\Enums\EstadoAjuste;
use App\Enums\EstadoPropiedad;
use App\Enums\EstadoTemaAdmin;
use App\Enums\Indice;
use App\Enums\MedioPago;
use App\Enums\RolUsuario;
use App\Enums\TipoGasto;
use App\Enums\TipoPropiedad;
use App\Models\AdminThread;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\IndexValue;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentAdjustment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ajustes\AplicadorDeAjuste;
use App\Services\Ajustes\GeneradorDePropuestas;
use App\Services\Cobranzas\GeneradorDeCargos;
use App\Services\Repartos\RepartidorEntreDuenos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Pablo Mandile',
            'email' => 'pablo.mandile@gmail.com',
            'password' => Hash::make('password'),
        ]);

        // Usuario para la demo del portfolio: entra con email y contraseña y ve
        // todo (rol admin). No tiene propiedades propias, así que no aparece en
        // liquidaciones.
        User::factory()->admin()->create([
            'name' => 'Usuario Demo',
            'email' => 'demo@alquilerfacil.com.ar',
            'password' => Hash::make('demo1234'),
        ]);

        // Un dueño con acceso a la app y otros dos que son sólo datos, que es el
        // caso real: no todos los copropietarios quieren una cuenta.
        $pablo = Owner::factory()->create([
            'user_id' => $admin->id,
            'nombre' => 'Pablo Mandile',
            'email' => $admin->email,
        ]);

        $usuarioSocia = User::factory()->create([
            'name' => 'Laura Giménez',
            'email' => 'laura@example.com',
            'password' => Hash::make('password'),
            'rol' => RolUsuario::Propietario,
        ]);

        $laura = Owner::factory()->create([
            'user_id' => $usuarioSocia->id,
            'nombre' => 'Laura Giménez',
            'email' => $usuarioSocia->email,
        ]);

        $hermano = Owner::factory()->create(['nombre' => 'Martín Mandile']);

        $this->indicesOficiales();
        $this->cabildo($pablo, $laura);
        $this->thames($pablo, $laura, $hermano);
        $this->local($pablo);

        // Con los índices cargados, calcular los ajustes que ya están en fecha
        // (Cabildo por IPC, Thames por ICL) y aplicar el de Cabildo: así queda
        // historial de ajustes y el de Thames pendiente de revisión.
        app(GeneradorDePropuestas::class)->generar();

        $ajuste = RentAdjustment::query()
            ->where('estado', EstadoAjuste::Propuesto)
            ->whereRelation('contract.property', 'alias', 'Cabildo 2300 4°B')
            ->first();

        if ($ajuste !== null) {
            app(AplicadorDeAjuste::class)->aplicar($ajuste);
        }

        // Emitir los cargos de los últimos meses para tener historia de cobranza.
        // Va después del ajuste para que los cargos ya salgan con el valor nuevo.
        $generador = app(GeneradorDeCargos::class);

        foreach ([3, 2, 1, 0] as $mesesAtras) {
            $generador->generar(today()->subMonths($mesesAtras));
        }

        $this->registrarPagos();
    }

    /**
     * Valores del IPC (INDEC, mensual) y del ICL (BCRA, diario) de 2026. Números
     * de referencia para que la demo tenga ajustes calculables y el panel de
     * índices con datos.
     */
    private function indicesOficiales(): void
    {
        $ipc = [
            '2026-01-01' => ['10800.0000', '0.021000'],
            '2026-02-01' => ['11000.0000', '0.018500'],
            '2026-03-01' => ['11150.0000', '0.013600'],
            '2026-04-01' => ['11363.0904', '0.019100'],
            '2026-05-01' => ['11607.3937', '0.021500'],
            '2026-06-01' => ['11826.4103', '0.018900'],
            '2026-07-01' => ['12076.3937', '0.021100'],
        ];

        foreach ($ipc as $fecha => [$valor, $variacion]) {
            IndexValue::factory()->ipc($fecha, $valor)->create(['variacion_mensual' => $variacion]);
        }

        // ICL: se publica un valor por día. Con uno por mes alcanza para que la
        // demo calcule (el cálculo toma el último valor disponible <= la fecha).
        $icl = [
            '2026-02-01' => '2315.00',
            '2026-03-01' => '2380.00',
            '2026-04-01' => '2455.00',
            '2026-05-01' => '2531.00',
            '2026-06-01' => '2608.00',
            '2026-07-01' => '2690.00',
            '2026-08-01' => '2776.00',
            '2026-09-01' => '2861.00',
        ];

        foreach ($icl as $fecha => $valor) {
            IndexValue::factory()->icl($fecha, $valor)->create(['variacion_mensual' => null]);
        }
    }

    /** Departamento a medias, con ajuste por IPC que ya está en fecha. */
    private function cabildo(Owner $pablo, Owner $laura): void
    {
        $property = Property::factory()->alquilada()->create([
            'alias' => 'Cabildo 2300 4°B',
            'calle' => 'Av. Cabildo',
            'numero' => '2300',
            'piso' => '4',
            'depto' => 'B',
            'ambientes' => 3,
            'superficie_m2' => 68,
        ]);

        $property->owners()->attach([
            $pablo->id => ['porcentaje' => 50],
            $laura->id => ['porcentaje' => 50],
        ]);

        $inquilino = Tenant::factory()->create(['nombre' => 'Sofía Ramírez']);

        // El ajuste con vigencia 1/8 usa el IPC de julio, que ya está publicado:
        // debería aparecer una propuesta lista para aplicar.
        Contract::factory()->create([
            'property_id' => $property->id,
            'tenant_id' => $inquilino->id,
            'fecha_inicio' => Date::parse('2025-08-01'),
            'fecha_fin' => Date::parse('2027-07-31'),
            'monto_base' => 450000,
            'monto_actual' => 450000,
            'dia_vencimiento' => 10,
            'deposito' => 380000,
            'indice' => Indice::Ipc,
            'frecuencia_meses' => 3,
            'proximo_ajuste' => Date::parse('2026-08-01'),
            'redondeo' => 1000,
        ]);

        Expense::factory()->create([
            'property_id' => $property->id,
            'tipo' => TipoGasto::Expensas,
            'categoria' => CategoriaGasto::Expensas,
            'descripcion' => 'Expensas ordinarias',
            'periodo' => today()->startOfMonth(),
            'monto' => 78500,
            'vencimiento' => today()->startOfMonth()->setDay(15),
            'a_cargo_de' => ACargoDe::Inquilino,
        ]);

        // Gasto compartido a medias: la mitad la paga el inquilino y la otra
        // se reparte entre los dos dueños.
        $service = Expense::factory()->compartido()->create([
            'property_id' => $property->id,
            'tipo' => TipoGasto::Servicio,
            'categoria' => CategoriaGasto::Gas,
            'descripcion' => 'Service anual de la caldera',
            'periodo' => today()->startOfMonth(),
            'monto' => 96000,
            'vencimiento' => today()->startOfMonth()->setDay(20),
        ]);
        app(RepartidorEntreDuenos::class)->repartir($service);

        // Seguimiento con la administración del edificio.
        $tema = AdminThread::factory()->create([
            'property_id' => $property->id,
            'titulo' => 'Filtración en el palier del 4° piso',
            'categoria' => CategoriaTemaAdmin::Reclamo,
            'estado' => EstadoTemaAdmin::Abierto,
        ]);

        $tema->entries()->createMany([
            [
                'fecha' => today()->subDays(18),
                'detalle' => 'Se informa a la administración por mail. Mancha de humedad en el techo del palier, cerca del ascensor.',
            ],
            [
                'fecha' => today()->subDays(9),
                'detalle' => 'La administración manda un plomero a revisar. Dice que viene de una cañería del 5°.',
            ],
            [
                'fecha' => today()->subDays(2),
                'detalle' => 'Pendiente el arreglo definitivo y el repintado. Reclamado de nuevo.',
            ],
        ]);
    }

    /** Departamento entre tres, con ajuste por ICL. */
    private function thames(Owner $pablo, Owner $laura, Owner $hermano): void
    {
        $property = Property::factory()->alquilada()->create([
            'alias' => 'Thames 1450 2°A',
            'calle' => 'Thames',
            'numero' => '1450',
            'piso' => '2',
            'depto' => 'A',
            'ambientes' => 2,
            'superficie_m2' => 47,
        ]);

        // Porcentajes que no dividen redondo: es justo el caso donde el reparto
        // ingenuo pierde centavos.
        $property->owners()->attach([
            $pablo->id => ['porcentaje' => 33.34],
            $laura->id => ['porcentaje' => 33.33],
            $hermano->id => ['porcentaje' => 33.33],
        ]);

        $inquilino = Tenant::factory()->create(['nombre' => 'Diego Fernández']);

        $contrato = Contract::factory()->porIcl()->create([
            'property_id' => $property->id,
            'tenant_id' => $inquilino->id,
            'fecha_inicio' => Date::parse('2025-03-01'),
            'fecha_fin' => Date::parse('2027-02-28'),
            'monto_base' => 412000,
            'monto_actual' => 412000,
            'dia_vencimiento' => 5,
            'deposito' => 300000,
            'frecuencia_meses' => 6,
            'proximo_ajuste' => Date::parse('2026-09-01'),
        ]);

        // Gasto extraordinario del mes pasado, ya pagado: se reparte entre los
        // tres dueños y aparece en la liquidación de ese mes.
        Expense::factory()->extraordinario()->create([
            'property_id' => $property->id,
            'contract_id' => $contrato->id,
            'descripcion' => 'Cambio del termotanque',
            'periodo' => today()->subMonth()->startOfMonth(),
            'monto' => 890000,
            'vencimiento' => today()->subMonth()->startOfMonth()->setDay(20),
            'pagado' => true,
            'fecha_pago' => today()->subMonth()->startOfMonth()->setDay(19),
        ]);

        Expense::factory()->create([
            'property_id' => $property->id,
            'contract_id' => $contrato->id,
            'categoria' => CategoriaGasto::Luz,
            'descripcion' => 'Edesur',
            'periodo' => today()->startOfMonth(),
            'monto' => 42300,
            'vencimiento' => today()->startOfMonth()->setDay(18),
        ]);
    }

    /** Local comercial de un solo dueño y sin contrato: está disponible. */
    private function local(Owner $pablo): void
    {
        $property = Property::factory()->create([
            'alias' => 'Local Juramento 2100',
            'tipo' => TipoPropiedad::Local,
            'estado' => EstadoPropiedad::Disponible,
            'calle' => 'Juramento',
            'numero' => '2100',
            'piso' => null,
            'depto' => null,
            'ambientes' => 1,
            'superficie_m2' => 95,
        ]);

        $property->owners()->attach($pablo->id, ['porcentaje' => 100]);

        Expense::factory()->create([
            'property_id' => $property->id,
            'tipo' => TipoGasto::Impuesto,
            'categoria' => CategoriaGasto::Abl,
            'descripcion' => 'ABL bimestral',
            'periodo' => today()->startOfMonth(),
            'monto' => 56000,
            'vencimiento' => today()->startOfMonth()->setDay(22),
            'a_cargo_de' => ACargoDe::Propietarios,
        ]);
    }

    /**
     * Los meses viejos quedan pagos (uno parcial, a propósito). Del mes en
     * curso, uno de los inquilinos ya pagó y el otro está pendiente: así se ven
     * los tres estados de cobranza.
     */
    private function registrarPagos(): void
    {
        Contract::query()->activos()->with(['charges', 'property'])->get()->each(function (Contract $contrato) {
            foreach ($contrato->charges as $indice => $cargo) {
                $esMesActual = $cargo->periodo->isSameMonth(today());

                // Del mes en curso sólo paga Thames; el resto de ese mes queda
                // pendiente.
                if ($esMesActual && $contrato->property->alias !== 'Thames 1450 2°A') {
                    continue;
                }

                // Uno de los cargos viejos queda con un pago parcial.
                $monto = $indice === 1 && ! $esMesActual
                    ? bcdiv((string) $cargo->monto, '2', 2)
                    : (string) $cargo->monto;

                Payment::factory()->de($monto)->create([
                    'rent_charge_id' => $cargo->id,
                    'fecha' => $esMesActual ? today()->setDay(4) : $cargo->vencimiento,
                    'medio' => MedioPago::Transferencia,
                ]);
            }
        });
    }
}
