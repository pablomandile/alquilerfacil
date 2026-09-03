<?php

namespace App\Http\Controllers;

use App\Enums\TipoDocumento;
use App\Models\Contract;
use App\Models\Tenant;
use App\Support\Opciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();

        $inquilinos = Tenant::query()
            // Un propietario ve sólo a los inquilinos de sus propiedades.
            ->when(! $usuario->esAdmin(), fn ($q) => $q->whereIn(
                'id',
                Contract::query()->visiblePara($usuario)->select('tenant_id')
            ))
            ->with(['contracts.property:id,alias'])
            ->orderBy('nombre')
            ->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'nombre' => $t->nombre,
                'documento' => $t->documento,
                'tipo_documento' => $t->tipo_documento?->label(),
                'email' => $t->email,
                'telefono' => $t->telefono,
                'propiedades' => $t->contracts->map(fn (Contract $c) => $c->property->alias)->unique()->values(),
            ]);

        return Inertia::render('inquilinos/Index', [
            'inquilinos' => $inquilinos,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('inquilinos/Form', [
            'tiposDocumento' => Opciones::de(TipoDocumento::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Tenant::query()->create($this->validado($request));

        return to_route('inquilinos.index')->with('success', 'Inquilino creado.');
    }

    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('inquilinos/Form', [
            'tiposDocumento' => Opciones::de(TipoDocumento::class),
            'inquilino' => [
                ...$tenant->only(['id', 'nombre', 'documento', 'email', 'telefono', 'notas']),
                'tipo_documento' => $tenant->tipo_documento?->value,
            ],
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($this->validado($request));

        return to_route('inquilinos.index')->with('success', 'Inquilino actualizado.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        if ($tenant->contracts()->exists()) {
            return back()->with('error', 'No se puede borrar: el inquilino tiene contratos cargados.');
        }

        $tenant->delete();

        return to_route('inquilinos.index')->with('success', 'Inquilino eliminado.');
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
            'notas' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
