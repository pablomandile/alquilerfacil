<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCargo;
use App\Enums\Indice;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\IndexValue;
use App\Models\Property;
use App\Models\RentAdjustment;
use App\Models\RentCharge;
use Illuminate\Http\Request;
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

        return Inertia::render('Dashboard', [
            'mes' => $mes->translatedFormat('F \d\e Y'),
            'cobranza' => [
                'facturado' => $facturado,
                'cobrado' => $cobrado,
                'pendiente' => bcsub($facturado, $cobrado, 2),
                'cargos' => $cargos->count(),
                'vencidos' => $cargos->where('estado', EstadoCargo::Vencido)->count(),
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
