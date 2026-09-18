<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaGasto;
use App\Enums\EstadoCargo;
use App\Enums\Indice;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\IndexValue;
use App\Models\Property;
use App\Models\RentAdjustment;
use App\Models\RentCharge;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $usuario = $request->user();
        $mes = today()->startOfMonth();

        $cargos = RentCharge::query()
            ->visiblePara($usuario)
            ->delPeriodo($mes)
            ->with('payments')
            ->get();

        $facturado = '0';
        $cobrado = '0';
        foreach ($cargos as $cargo) {
            $facturado = bcadd($facturado, $cargo->monto, 2);
            $cobrado = bcadd($cobrado, $cargo->totalPagado(), 2);
        }

        // Acumulado del alquiler por propiedad: lo cobrado y el neto contra los
        // gastos de los dueños, como en la ficha de cada propiedad. Sólo las que
        // ya tienen movimiento.
        $totalesPorPropiedad = Property::query()
            ->visiblePara($usuario)
            ->with('contracts.charges.payments')
            ->orderBy('alias')
            ->get()
            ->map(fn (Property $p) => [...$p->totalesDeAlquiler(), 'id' => $p->id, 'alias' => $p->alias])
            ->filter(fn (array $t) => $t['facturado'] !== '0.00'
                || $t['gastos_ordinarios'] !== '0.00'
                || $t['gastos_extraordinarios'] !== '0.00')
            ->sortByDesc(fn (array $t) => (float) $t['neto'])
            ->values();

        // Todo lo gastado por propiedad y categoría, sin mirar quién lo paga:
        // para ver a dónde se va la plata.
        $gastos = Expense::query()
            ->visiblePara($usuario)
            ->selectRaw('property_id, categoria, SUM(monto) as total')
            ->groupBy('property_id', 'categoria')
            ->with('property:id,alias')
            ->get()
            ->map(fn (Expense $g) => [
                'property_id' => $g->property_id,
                'alias' => $g->property->alias,
                'categoria' => $g->categoria,
                'monto' => Decimal::desde($g->getAttribute('total')),
            ])
            ->filter(fn (array $g) => bccomp($g['monto'], '0', 2) > 0);

        // El color de cada categoría sale de su lugar en el enum y no de cuánto
        // suma: así una categoría no cambia de color al cargar un gasto nuevo,
        // y las porciones que se tocan son, salvo salteo, los pares que validó
        // la guía de gráficos. Las tortas y la leyenda usan este mismo orden.
        $orden = collect(CategoriaGasto::cases());

        $gastosPorPropiedad = $gastos
            ->groupBy('property_id')
            ->map(function (Collection $grupo, int $propertyId) use ($orden) {
                // Los gastos en cero ya quedaron afuera, así que alcanza con
                // mirar qué categorías tiene esta propiedad.
                $claves = $grupo->map(fn (array $g) => $g['categoria']->value);

                return [
                    'id' => $propertyId,
                    'alias' => $grupo->first()['alias'],
                    'total' => Decimal::sumar($grupo->pluck('monto')),
                    'categorias' => $orden
                        ->map(fn (CategoriaGasto $c, int $n) => [
                            'clave' => $c->value,
                            'etiqueta' => $c->label(),
                            'color' => $n + 1,
                            'monto' => Decimal::sumar(
                                $grupo->filter(fn (array $g) => $g['categoria'] === $c)->pluck('monto')
                            ),
                        ])
                        ->filter(fn (array $c) => $claves->contains($c['clave']))
                        ->values(),
                ];
            })
            ->sortByDesc(fn (array $g) => (float) $g['total'])
            ->values();

        // La leyenda es una sola para todas las tortas, y sólo nombra las
        // categorías que aparecen en alguna.
        $presentes = $gastos->map(fn (array $g) => $g['categoria']);

        $leyendaDeGastos = $orden
            ->map(fn (CategoriaGasto $c, int $n) => [
                'clave' => $c->value,
                'etiqueta' => $c->label(),
                'color' => $n + 1,
            ])
            ->filter(fn (array $c) => $presentes->contains('value', $c['clave']))
            ->values();

        return Inertia::render('Dashboard', [
            'mes' => $mes->translatedFormat('F \d\e Y'),
            'cobranza' => [
                'facturado' => $facturado,
                'cobrado' => $cobrado,
                'pendiente' => bcsub($facturado, $cobrado, 2),
                'cargos' => $cargos->count(),
                'vencidos' => $cargos->where('estado', EstadoCargo::Vencido)->count(),
            ],
            'alquileres' => [
                'por_propiedad' => $totalesPorPropiedad->map(fn (array $t) => [
                    'id' => $t['id'],
                    'alias' => $t['alias'],
                    'cobrado' => $t['cobrado'],
                    'neto' => $t['neto'],
                ]),
                'facturado' => Decimal::sumar($totalesPorPropiedad->pluck('facturado')),
                'cobrado' => Decimal::sumar($totalesPorPropiedad->pluck('cobrado')),
                'gastos_ordinarios' => Decimal::sumar($totalesPorPropiedad->pluck('gastos_ordinarios')),
                'gastos_extraordinarios' => Decimal::sumar($totalesPorPropiedad->pluck('gastos_extraordinarios')),
                'neto' => Decimal::sumar($totalesPorPropiedad->pluck('neto')),
            ],
            'gastos' => [
                'por_propiedad' => $gastosPorPropiedad,
                'leyenda' => $leyendaDeGastos,
                'total' => Decimal::sumar($gastosPorPropiedad->pluck('total')),
            ],
            'resumen' => [
                'propiedades' => Property::query()->visiblePara($usuario)->count(),
                'contratos_activos' => Contract::query()->visiblePara($usuario)->activos()->count(),
                'ajustes_propuestos' => RentAdjustment::query()->visiblePara($usuario)->propuestos()->count(),
                'gastos_impagos' => Expense::query()->visiblePara($usuario)->impagos()->count(),
            ],
            // Los contratos que ya llegaron a su fecha de ajuste, para que se vea
            // desde la portada que hay plata sin actualizar.
            'ajustesPendientes' => Contract::query()
                ->visiblePara($usuario)
                ->conAjustePendiente()
                ->with(['property:id,alias'])
                ->get()
                ->map(fn (Contract $c) => [
                    'id' => $c->id,
                    'propiedad' => $c->property->alias,
                    'monto_actual' => $c->monto_actual,
                    'fecha' => $c->proximo_ajuste->format('d/m/Y'),
                    'indice' => $c->indice->labelCorto(),
                ]),
            'gastosPorVencer' => Expense::query()
                ->visiblePara($usuario)
                ->impagos()
                ->whereNotNull('vencimiento')
                ->orderBy('vencimiento')
                ->with('property:id,alias')
                ->limit(6)
                ->get()
                ->map(fn (Expense $g) => [
                    'id' => $g->id,
                    'propiedad' => $g->property->alias,
                    'descripcion' => $g->descripcion ?: $g->categoria->label(),
                    'monto' => $g->monto,
                    'vencimiento' => $g->vencimiento->format('d/m/Y'),
                    'vencido' => $g->estaVencido(),
                ]),
            'indices' => collect([Indice::Ipc, Indice::Icl])->map(function (Indice $indice) {
                $ultimo = IndexValue::query()->de($indice)->orderByDesc('fecha')->first();

                return [
                    'nombre' => $indice->labelCorto(),
                    'fecha' => $ultimo?->fecha->translatedFormat($indice->esMensual() ? 'F Y' : 'd/m/Y'),
                    'valor' => $ultimo ? (float) $ultimo->valor : null,
                    'variacion' => $ultimo?->variacion_mensual !== null
                        ? round((float) $ultimo->variacion_mensual * 100, 2)
                        : null,
                ];
            }),
        ]);
    }
}
