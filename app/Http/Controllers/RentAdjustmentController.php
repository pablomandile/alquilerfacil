<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAjuste;
use App\Models\RentAdjustment;
use App\Services\Ajustes\AplicadorDeAjuste;
use App\Services\Ajustes\GeneradorDePropuestas;
use App\Services\Ajustes\PropuestaDeAjuste;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pantalla donde la app ofrece los aumentos y el usuario decide.
 *
 * Nada de acá cambia un alquiler por su cuenta: aplicar es siempre un clic
 * explícito, y el monto se puede editar antes de confirmar.
 */
class RentAdjustmentController extends Controller
{
    public function index(Request $request, GeneradorDePropuestas $generador): Response
    {
        // Al entrar se recalcula: si el INDEC publicó un índice desde la última
        // visita, la propuesta que estaba en espera aparece sin hacer nada.
        $enEspera = $request->user()->esAdmin()
            ? $generador->generar()
                ->reject(fn ($r) => $r instanceof PropuestaDeAjuste)
                ->map(fn ($r) => $r->motivo())
                ->values()
            : collect();

        $ajustes = RentAdjustment::query()
            ->visiblePara($request->user())
            ->with(['contract.property:id,alias', 'contract.tenant:id,nombre'])
            ->orderByDesc('vigencia_desde')
            ->get()
            ->map(fn (RentAdjustment $a) => [
                'id' => $a->id,
                'contrato_id' => $a->contract_id,
                'propiedad' => $a->contract->property->alias,
                'inquilino' => $a->contract->tenant->nombre,
                'vigencia' => $a->vigencia_desde->format('d/m/Y'),
                'monto_anterior' => $a->monto_anterior,
                'monto_nuevo' => $a->monto_nuevo,
                'diferencia' => $a->diferencia(),
                'variacion' => (float) $a->variacion_porcentual,
                'indice' => $a->indice->labelCorto(),
                'ventana' => $a->periodo_indice_desde->format('m/Y').' a '.$a->periodo_indice_hasta->format('m/Y'),
                'estado' => $a->estado->value,
                'estado_label' => $a->estado->label(),
                'notas' => $a->notas,
            ]);

        return Inertia::render('ajustes/Index', [
            'propuestos' => $ajustes->where('estado', EstadoAjuste::Propuesto->value)->values(),
            'historial' => $ajustes->where('estado', '!=', EstadoAjuste::Propuesto->value)->values(),
            'enEspera' => $enEspera,
        ]);
    }

    public function recalcular(GeneradorDePropuestas $generador): RedirectResponse
    {
        $resultados = $generador->generar();
        $nuevas = $resultados->filter(fn ($r) => $r instanceof PropuestaDeAjuste)->count();

        return back()->with(
            'success',
            $nuevas === 0
                ? 'No hay ajustes nuevos para proponer.'
                : "Se calcularon {$nuevas} ajustes."
        );
    }

    public function aplicar(Request $request, RentAdjustment $adjustment, AplicadorDeAjuste $aplicador): RedirectResponse
    {
        $this->authorize('resolver', $adjustment);

        if (! $adjustment->estaPropuesto()) {
            return back()->with('error', 'Ese ajuste ya fue resuelto.');
        }

        $datos = $request->validate([
            'monto' => ['nullable', 'numeric', 'min:0'],
        ]);

        $aplicado = $aplicador->aplicar($adjustment, $datos['monto'] ?? null);

        return back()->with('success', sprintf(
            'Alquiler actualizado a $%s desde el %s.',
            number_format((float) $aplicado->monto_nuevo, 2, ',', '.'),
            $aplicado->vigencia_desde->format('d/m/Y'),
        ));
    }

    public function rechazar(Request $request, RentAdjustment $adjustment, AplicadorDeAjuste $aplicador): RedirectResponse
    {
        $this->authorize('resolver', $adjustment);

        if (! $adjustment->estaPropuesto()) {
            return back()->with('error', 'Ese ajuste ya fue resuelto.');
        }

        $datos = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $aplicador->rechazar($adjustment, $datos['motivo'] ?? null);

        return back()->with('success', 'Ajuste rechazado. El alquiler queda como estaba.');
    }
}
