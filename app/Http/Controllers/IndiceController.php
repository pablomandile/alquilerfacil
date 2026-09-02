<?php

namespace App\Http\Controllers;

use App\Enums\Indice;
use App\Models\IndexValue;
use App\Services\Indices\SincronizadorDeIndices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('indices/Index', [
            'series' => collect([Indice::Ipc, Indice::Icl])->map(fn (Indice $indice) => [
                'fuente' => $indice->value,
                'nombre' => $indice->label(),
                'esMensual' => $indice->esMensual(),
                'ultimaFecha' => IndexValue::ultimaFecha($indice)?->format('d/m/Y'),
                'total' => IndexValue::query()->de($indice)->count(),
                'valores' => IndexValue::query()
                    ->de($indice)
                    ->orderByDesc('fecha')
                    ->limit($indice->esMensual() ? 18 : 30)
                    ->get()
                    ->map(fn (IndexValue $v) => [
                        'fecha' => $indice->esMensual()
                            ? $v->fecha->translatedFormat('M Y')
                            : $v->fecha->format('d/m/Y'),
                        'valor' => (float) $v->valor,
                        'variacion' => $v->variacion_mensual !== null
                            ? round((float) $v->variacion_mensual * 100, 2)
                            : null,
                    ])
                    ->reverse()
                    ->values(),
            ]),
        ]);
    }

    public function sincronizar(Request $request, SincronizadorDeIndices $sincronizador): RedirectResponse
    {
        $datos = $request->validate([
            'fuente' => ['nullable', 'in:ipc,icl'],
        ]);

        $resultados = $sincronizador->sincronizar(
            isset($datos['fuente']) ? Indice::from($datos['fuente']) : null
        );

        $fallidos = $resultados->reject(fn ($r) => $r->exitoso());

        if ($fallidos->isNotEmpty()) {
            return back()->with('error', $fallidos->map(fn ($r) => $r->resumen())->implode(' '));
        }

        return back()->with('success', $resultados->map(fn ($r) => $r->resumen())->implode(' '));
    }
}
