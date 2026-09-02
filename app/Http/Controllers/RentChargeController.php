<?php

namespace App\Http\Controllers;

use App\Enums\MedioPago;
use App\Models\RentCharge;
use App\Services\Cobranzas\GeneradorDeCargos;
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

        $cargos = RentCharge::query()
            ->visiblePara($request->user())
            ->delPeriodo($periodo)
            ->with(['contract.property:id,alias', 'contract.tenant:id,nombre', 'payments'])
            ->get()
            ->map(fn (RentCharge $c) => [
                'id' => $c->id,
                'propiedad' => $c->contract->property->alias,
                'contrato_id' => $c->contract_id,
                'inquilino' => $c->contract->tenant->nombre,
                'monto' => $c->monto,
                'pagado' => $c->totalPagado(),
                'saldo' => $c->saldo(),
                'vencimiento' => $c->vencimiento->format('d/m/Y'),
                'estado' => $c->estado->value,
                'estado_label' => $c->estado->label(),
                'pagos' => $c->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'fecha' => $p->fecha->format('d/m/Y'),
                    'monto' => $p->monto,
                    'medio' => $p->medio->label(),
                    'referencia' => $p->referencia,
                ]),
            ])
            ->sortBy('propiedad')
            ->values();

        return Inertia::render('cobranzas/Index', [
            'cargos' => $cargos,
            'periodo' => $periodo->format('Y-m'),
            'periodoLabel' => $periodo->translatedFormat('F \d\e Y'),
            'totales' => [
                'facturado' => $cargos->reduce(fn ($a, $c) => bcadd($a, (string) $c['monto'], 2), '0'),
                'cobrado' => $cargos->reduce(fn ($a, $c) => bcadd($a, $c['pagado'], 2), '0'),
                'pendiente' => $cargos->reduce(fn ($a, $c) => bcadd($a, $c['saldo'], 2), '0'),
            ],
            'mediosPago' => Opciones::de(MedioPago::class),
        ]);
    }

    public function generar(Request $request, GeneradorDeCargos $generador): RedirectResponse
    {
        $datos = $request->validate([
            'periodo' => ['nullable', 'date_format:Y-m'],
        ]);

        $periodo = isset($datos['periodo'])
            ? Date::parse($datos['periodo'].'-01')
            : today();

        $resultados = $generador->generar($periodo);
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
}
