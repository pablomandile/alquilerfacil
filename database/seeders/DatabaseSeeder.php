<?php

namespace Database\Seeders;

use App\Enums\ACargoDe;
use App\Enums\CategoriaGasto;
use App\Enums\EstadoPropiedad;
use App\Enums\Indice;
use App\Enums\MedioPago;
use App\Enums\RolUsuario;
use App\Enums\TipoGasto;
use App\Enums\TipoPropiedad;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Cobranzas\GeneradorDeCargos;
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

        $this->cabildo($pablo, $laura);
        $this->thames($pablo, $laura, $hermano);
        $this->local($pablo);

        // Emitir los cargos de los últimos meses para tener historia de cobranza.
        $generador = app(GeneradorDeCargos::class);

        foreach ([3, 2, 1, 0] as $mesesAtras) {
            $generador->generar(today()->subMonths($mesesAtras));
        }

        $this->registrarPagos();
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
            'monto_base' => 380000,
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
            'monto_base' => 300000,
            'monto_actual' => 412000,
            'dia_vencimiento' => 5,
            'deposito' => 300000,
            'frecuencia_meses' => 6,
            'proximo_ajuste' => Date::parse('2026-09-01'),
        ]);

        // Gasto extraordinario: se reparte entre los tres dueños.
        Expense::factory()->extraordinario()->create([
            'property_id' => $property->id,
            'contract_id' => $contrato->id,
            'descripcion' => 'Cambio del termotanque',
            'periodo' => today()->subMonth()->startOfMonth(),
            'monto' => 890000,
            'vencimiento' => today()->subMonth()->startOfMonth()->setDay(20),
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

    /** Deja los meses viejos pagos y el actual a medias, para ver los estados. */
    private function registrarPagos(): void
    {
        Contract::query()->activos()->with('charges')->get()->each(function (Contract $contrato) {
            foreach ($contrato->charges as $indice => $cargo) {
                if ($cargo->periodo->isSameMonth(today())) {
                    continue; // El mes en curso queda pendiente.
                }

                // Uno de los cargos queda con un pago parcial, a propósito.
                $monto = $indice === 1
                    ? bcdiv((string) $cargo->monto, '2', 2)
                    : (string) $cargo->monto;

                Payment::factory()->de($monto)->create([
                    'rent_charge_id' => $cargo->id,
                    'fecha' => $cargo->vencimiento,
                    'medio' => MedioPago::Transferencia,
                ]);
            }
        });
    }
}
