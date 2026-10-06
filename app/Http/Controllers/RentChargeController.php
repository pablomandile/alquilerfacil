<?php

namespace App\Http\Controllers;

use App\Enums\ACargoDe;
use App\Enums\MedioPago;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\TenantMessage;
use App\Services\Cobranzas\GeneradorDeCargos;
use App\Support\Decimal;
use App\Support\Opciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class RentChargeController extends Controller
{
    public function index(Request $request): Response
    {
        $datos = $request->validate([
            'periodo' => ['nullable', 'date_format:Y-m'],
        ]);

        $periodo = isset($datos['periodo'])
            ? Date::parse($datos['periodo'].'-01')
            : today()->startOfMonth();

        $rawCargos = RentCharge::query()
            ->visiblePara($request->user())
            ->delPeriodo($periodo)
            ->with(['contract.property:id,alias', 'contract.tenant:id,nombre,telefono', 'payments'])
            ->get();

        $propertyIds = $rawCargos->pluck('contract.property_id')->unique()->values();

        $gastosPorPropiedad = Expense::query()
            ->whereIn('property_id', $propertyIds)
            ->whereIn('a_cargo_de', [ACargoDe::Inquilino, ACargoDe::Mitades])
            ->whereNotNull('vencimiento')
            ->whereMonth('vencimiento', $periodo->month)
            ->whereYear('vencimiento', $periodo->year)
            ->orderBy('vencimiento')
            ->with('documents')
            ->get()
            ->groupBy('property_id');

        $enviosPorPropiedad = TenantMessage::query()
            ->whereIn('property_id', $propertyIds)
            ->delPeriodo($periodo)
            ->get()
            ->keyBy('property_id');

        $cargos = $rawCargos
            ->map(fn (RentCharge $c) => [
                'id' => $c->id,
                'propiedad' => $c->contract->property->alias,
                'contrato_id' => $c->contract_id,
                'property_id' => $c->contract->property_id,
                'inquilino' => $c->contract->tenant->nombre,
                'telefono' => $c->contract->tenant->telefono,
                'monto' => $c->monto,
                'pagado' => $c->totalPagado(),
                'saldo' => $c->saldo(),
                'vencimiento' => $c->vencimiento->format('d/m/Y'),
                'estado' => $c->estado->value,
                'estado_label' => $c->estado->label(),
                'pagos' => $c->payments->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'fecha' => $p->fecha->format('d/m/Y'),
                    'monto' => $p->monto,
                    'medio' => $p->medio->label(),
                    'referencia' => $p->referencia,
                    'comprobante' => $p->comprobante_path !== null
                        ? ['nombre' => $p->comprobante_nombre, 'mime' => $p->comprobante_mime]
                        : null,
                ])->all(),
                'mes' => $periodo->translatedFormat('F \d\e Y'),
                'alquiler' => [
                    'concepto' => 'Alquiler '.$periodo->translatedFormat('F'),
                    'monto' => $c->monto,
                    'vencimiento' => $c->vencimiento->format('d/m/Y'),
                ],
                'gastos' => ($gastosPorPropiedad->get($c->contract->property_id) ?? collect())
                    ->map(fn (Expense $g) => $g->paraElInquilino())->values()->all(),
                'envio' => [
                    'enviado' => $enviosPorPropiedad->has($c->contract->property_id),
                    'fecha' => $enviosPorPropiedad->get($c->contract->property_id)?->enviado_at->format('d/m/Y'),
                ],
            ])
            ->sortBy('propiedad')
            ->values()
            ->all();

        return Inertia::render('cobranzas/Index', [
            'cargos' => $cargos,
            'periodo' => $periodo->format('Y-m'),
            'periodoLabel' => $periodo->translatedFormat('F \d\e Y'),
            'totales' => [
                'facturado' => Decimal::sumar(array_column($cargos, 'monto')),
                'cobrado' => Decimal::sumar(array_column($cargos, 'pagado')),
                'pendiente' => Decimal::sumar(array_column($cargos, 'saldo')),
            ],
            'mediosPago' => Opciones::de(MedioPago::class),
        ]);
    }

    public function generar(Request $request, GeneradorDeCargos $generador): RedirectResponse
    {
        $this->authorize('generar', RentCharge::class);

        $datos = $request->validate([
            'periodo' => ['nullable', 'date_format:Y-m'],
        ]);

        $periodo = isset($datos['periodo'])
            ? Date::parse($datos['periodo'].'-01')
            : today();

        $resultados = $generador->generar($periodo, $request->user());
        $nuevos = $resultados->filter(fn ($r) => $r->nuevo)->count();
        $fallidos = $resultados->reject(fn ($r) => $r->exitoso());

        if ($fallidos->isNotEmpty()) {
            return back()->with('error', $fallidos->map(fn ($f) => $f->error)->implode(' '));
        }

        return back()->with(
            'success',
            $nuevos === 0
                ? 'Los cargos de este mes ya estaban emitidos.'
                : "Se emitieron {$nuevos} cargos."
        );
    }

    public function destroy(RentCharge $charge): RedirectResponse
    {
        $this->authorize('delete', $charge);

        if ($charge->payments()->exists()) {
            return back()->with('error', 'No se puede borrar: el cargo tiene pagos registrados. Borrá el pago primero.');
        }

        $charge->shares()->delete();
        $charge->delete();

        return back()->with('success', 'Cargo eliminado.');
    }
}
