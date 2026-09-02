<?php

namespace App\Http\Controllers;

use App\Enums\TipoDocumento;
use App\Models\Owner;
use App\Support\Opciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OwnerController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();

        $propietarios = Owner::query()
            // Un propietario se ve sólo a sí mismo: no tiene por qué conocer los
            // datos de contacto ni el CBU de los otros dueños.
            ->when(! $usuario->esAdmin(), fn ($q) => $q->where('user_id', $usuario->id))
            ->withCount('properties')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Owner $o) => [
                'id' => $o->id,
                'nombre' => $o->nombre,
                'documento' => $o->documento,
                'tipo_documento' => $o->tipo_documento?->label(),
                'email' => $o->email,
                'telefono' => $o->telefono,
                'cbu' => $o->cbu,
                'alias_cbu' => $o->alias_cbu,
                'propiedades' => $o->properties_count,
                'tiene_acceso' => $o->tieneAcceso(),
            ]);

        return Inertia::render('propietarios/Index', [
            'propietarios' => $propietarios,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('propietarios/Form', [
            'tiposDocumento' => Opciones::de(TipoDocumento::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Owner::query()->create($this->validado($request));

        return to_route('propietarios.index')->with('success', 'Propietario creado.');
    }

    public function edit(Owner $owner): Response
    {
        return Inertia::render('propietarios/Form', [
            'tiposDocumento' => Opciones::de(TipoDocumento::class),
            'propietario' => [
                ...$owner->only(['id', 'nombre', 'documento', 'email', 'telefono', 'cbu', 'alias_cbu', 'notas']),
                'tipo_documento' => $owner->tipo_documento?->value,
            ],
        ]);
    }

    public function update(Request $request, Owner $owner): RedirectResponse
    {
        $owner->update($this->validado($request));

        return to_route('propietarios.index')->with('success', 'Propietario actualizado.');
    }

    public function destroy(Owner $owner): RedirectResponse
    {
        if ($owner->properties()->exists()) {
            return back()->with('error', 'No se puede borrar: el propietario tiene propiedades asociadas.');
        }

        $owner->delete();

        return to_route('propietarios.index')->with('success', 'Propietario eliminado.');
    }

    /** @return array<string, mixed> */
    private function validado(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['nullable', Rule::enum(TipoDocumento::class)],
            'documento' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'cbu' => ['nullable', 'string', 'max:22'],
            'alias_cbu' => ['nullable', 'string', 'max:50'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
