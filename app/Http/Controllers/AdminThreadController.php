<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaTemaAdmin;
use App\Enums\EstadoTemaAdmin;
use App\Http\Controllers\Concerns\RecibeArchivosAdjuntos;
use App\Models\AdminThread;
use App\Models\Property;
use App\Services\Administracion\RegistradorDeEntradas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminThreadController extends Controller
{
    use RecibeArchivosAdjuntos;

    public function store(Request $request, Property $property, RegistradorDeEntradas $registrador): RedirectResponse
    {
        $this->authorize('create', [AdminThread::class, $property]);

        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'categoria' => ['required', Rule::enum(CategoriaTemaAdmin::class)],
            'fecha' => ['required', 'date'],
            'detalle' => ['required', 'string', 'max:5000'],
            ...$this->reglasDeArchivos(),
        ]);

        DB::transaction(function () use ($request, $property, $datos, $registrador) {
            $tema = $property->adminThreads()->create([
                'titulo' => $datos['titulo'],
                'categoria' => $datos['categoria'],
                'estado' => EstadoTemaAdmin::Abierto,
            ]);

            $registrador->registrar(
                $tema,
                $datos['fecha'],
                $datos['detalle'],
                $this->archivos($request),
                $request->user(),
            );
        });

        return back()->with('success', 'Tema registrado.');
    }

    public function update(Request $request, AdminThread $thread): RedirectResponse
    {
        $this->authorize('update', $thread);

        $datos = $request->validate([
            'estado' => ['required', Rule::enum(EstadoTemaAdmin::class)],
        ]);

        $resuelto = $datos['estado'] === EstadoTemaAdmin::Resuelto->value;

        $thread->update([
            'estado' => $datos['estado'],
            'resuelto_at' => $resuelto ? now() : null,
        ]);

        return back()->with('success', $resuelto ? 'Tema marcado como resuelto.' : 'Tema reabierto.');
    }

    public function destroy(AdminThread $thread): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $thread->delete();

        return back()->with('success', 'Tema eliminado.');
    }
}
