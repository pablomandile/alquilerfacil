<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Owner;
use App\Models\RentCharge;
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

                $facturado = $this->sumar($alquileres, 'monto');
                $cobrado = $this->sumar($alquileres, 'cobrado');
                $gastado = $this->sumar($suGasto, 'monto');

                return [
                    'id' => $owner->id,
                    'nombre' => $owner->nombre,
                    'alquileres' => $alquileres,
                    'gastos' => $suGasto,
                    'facturado' => $facturado,
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
     * @return list<array<string, mixed>>
     */
    private function detalleDeAlquileres(Owner $owner, $cargos): array
    {
        return $cargos
            ->flatMap(fn (RentCharge $cargo) => $cargo->shares
                ->where('owner_id', $owner->id)
                ->map(fn ($share) => [
                    'propiedad' => $cargo->contract->property->alias,
                    'porcentaje' => (float) $share->porcentaje,
                    'monto' => (string) $share->monto,
                    // La plata le llega al dueño en la misma proporción en que el
                    // inquilino pagó: si pagó la mitad, le toca la mitad.
                    'cobrado' => $this->proporcional(
                        (string) $share->monto,
                        $cargo->totalPagado(),
                        (string) $cargo->monto
                    ),
                    'estado' => $cargo->estado->label(),
                ])
            )
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function detalleDeGastos(Owner $owner, $gastos): array
    {
        return $gastos
            ->flatMap(fn (Expense $gasto) => $gasto->shares
                ->where('owner_id', $owner->id)
                ->map(fn ($share) => [
                    'propiedad' => $gasto->property->alias,
                    'descripcion' => $gasto->descripcion ?: $gasto->categoria->label(),
                    'porcentaje' => (float) $share->porcentaje,
                    'monto' => (string) $share->monto,
                    'pagado' => $gasto->pagado,
                ])
            )
            ->values()
            ->all();
    }

    private function proporcional(string $parte, string $pagado, string $total): string
    {
        if (bccomp($total, '0', 2) === 0) {
            return '0.00';
        }

        return bcdiv(bcmul($parte, $pagado, 8), $total, 2);
    }

    /** @param  list<array<string, mixed>>  $filas */
    private function sumar(array $filas, string $campo): string
    {
        return array_reduce(
            $filas,
            fn (string $acc, array $fila) => bcadd($acc, (string) $fila[$campo], 2),
            '0'
        );
    }
}
