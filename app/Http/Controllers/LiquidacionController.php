<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Owner;
use App\Models\RentCharge;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cuánto le corresponde a cada dueño en un mes: su parte del alquiler menos su
 * parte de los gastos que van a cargo de los propietarios.
 *
 * Se arma sobre `owner_shares`, que guarda el reparto tal como se calculó en su
 * momento. Por eso una liquidación vieja no cambia aunque hoy los porcentajes de
 * propiedad sean otros.
 */
class LiquidacionController extends Controller
{
    public function index(Request $request): Response
    {
        $datos = $request->validate([
            'periodo' => ['nullable', 'date_format:Y-m'],
        ]);

        $periodo = isset($datos['periodo'])
            ? Date::parse($datos['periodo'].'-01')
            : today()->startOfMonth();

        $usuario = $request->user();

        $cargos = RentCharge::query()
            ->visiblePara($usuario)
            ->delPeriodo($periodo)
            ->with(['shares.owner:id,nombre', 'contract.property:id,alias', 'payments'])
            ->get();

        $gastos = Expense::query()
            ->visiblePara($usuario)
            ->aCargoDeLosPropietarios()
            ->whereYear('periodo', $periodo->year)
            ->whereMonth('periodo', $periodo->month)
            ->with(['shares.owner:id,nombre', 'property:id,alias'])
            ->get();

        $propietarios = Owner::query()
            ->when(! $usuario->esAdmin(), fn ($q) => $q->where('user_id', $usuario->id))
            ->orderBy('nombre')
            ->get()
            ->map(function (Owner $owner) use ($cargos, $gastos) {
                $alquileres = $this->detalleDeAlquileres($owner, $cargos);
                $suGasto = $this->detalleDeGastos($owner, $gastos);

                $cobrado = Decimal::sumar(array_column($alquileres, 'cobrado'));
                $gastado = Decimal::sumar(array_column($suGasto, 'monto'));

                return [
                    'id' => $owner->id,
                    'nombre' => $owner->nombre,
                    'alquileres' => $alquileres,
                    'gastos' => $suGasto,
                    'facturado' => Decimal::sumar(array_column($alquileres, 'monto')),
                    'cobrado' => $cobrado,
                    'gastos_total' => $gastado,
                    // Lo que efectivamente le queda: lo que entró menos lo que puso.
                    'neto' => bcsub($cobrado, $gastado, 2),
                ];
            })
            ->reject(fn (array $o) => $o['alquileres'] === [] && $o['gastos'] === [])
            ->values();

        return Inertia::render('liquidaciones/Index', [
            'propietarios' => $propietarios,
            'periodo' => $periodo->format('Y-m'),
            'periodoLabel' => $periodo->translatedFormat('F \d\e Y'),
        ]);
    }

    /**
     * La parte del alquiler de cada propiedad, con cuánto de eso ya se cobró.
     *
     * @param  Collection<int, RentCharge>  $cargos
     * @return list<array{propiedad: string, porcentaje: float, monto: numeric-string, cobrado: numeric-string, estado: string}>
     */
    private function detalleDeAlquileres(Owner $owner, Collection $cargos): array
    {
        $filas = [];

        foreach ($cargos as $cargo) {
            foreach ($cargo->shares->where('owner_id', $owner->id) as $share) {
                $filas[] = [
                    'propiedad' => $cargo->contract->property->alias,
                    'porcentaje' => (float) $share->porcentaje,
                    'monto' => $share->monto,
                    // La plata le llega al dueño en la misma proporción en que el
                    // inquilino pagó: si pagó la mitad, le toca la mitad.
                    'cobrado' => $this->proporcional(
                        $share->monto,
                        $cargo->totalPagado(),
                        $cargo->monto
                    ),
                    'estado' => $cargo->estado->label(),
                ];
            }
        }

        return $filas;
    }

    /**
     * @param  Collection<int, Expense>  $gastos
     * @return list<array{propiedad: string, descripcion: string, porcentaje: float, monto: numeric-string, pagado: bool}>
     */
    private function detalleDeGastos(Owner $owner, Collection $gastos): array
    {
        $filas = [];

        foreach ($gastos as $gasto) {
            foreach ($gasto->shares->where('owner_id', $owner->id) as $share) {
                $filas[] = [
                    'propiedad' => $gasto->property->alias,
                    'descripcion' => $gasto->descripcion ?: $gasto->categoria->label(),
                    'porcentaje' => (float) $share->porcentaje,
                    'monto' => $share->monto,
                    'pagado' => $gasto->pagado,
                ];
            }
        }

        return $filas;
    }

    /**
     * @param  numeric-string  $parte
     * @param  numeric-string  $pagado
     * @param  numeric-string  $total
     * @return numeric-string
     */
    private function proporcional(string $parte, string $pagado, string $total): string
    {
        if (bccomp($total, '0', 2) === 0) {
            return '0.00';
        }

        return bcdiv(bcmul($parte, $pagado, 8), $total, 2);
    }
}
