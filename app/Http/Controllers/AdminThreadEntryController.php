<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecibeArchivosAdjuntos;
use App\Models\AdminThread;
use App\Models\AdminThreadEntry;
use App\Services\Administracion\RegistradorDeEntradas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminThreadEntryController extends Controller
{
    use RecibeArchivosAdjuntos;

    public function store(Request $request, AdminThread $thread, RegistradorDeEntradas $registrador): RedirectResponse
    {
        $this->authorize('create', [AdminThreadEntry::class, $thread]);

        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'detalle' => ['required', 'string', 'max:5000'],
            ...$this->reglasDeArchivos(),
        ]);

        $registrador->registrar(
            $thread,
            $datos['fecha'],
            $datos['detalle'],
            $this->archivos($request),
            $request->user(),
        );

        return back()->with('success', 'Entrada agregada.');
    }

    public function destroy(AdminThreadEntry $entry): RedirectResponse
    {
        $this->authorize('delete', $entry);

        // Un tema sin ninguna entrada no tiene sentido: borrar la última entrada
        // es borrar el tema.
        if ($entry->thread->entries()->count() === 1) {
            $entry->thread->delete();

            return back()->with('success', 'Tema eliminado.');
        }

        $entry->delete();

        return back()->with('success', 'Entrada eliminada.');
    }
}
