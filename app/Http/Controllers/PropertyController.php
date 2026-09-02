<?php

namespace App\Http\Controllers;

use App\Enums\EstadoPropiedad;
use App\Enums\TipoPropiedad;
use App\Http\Requests\PropertyRequest;
use App\Models\Owner;
use App\Models\Property;
use App\Support\Opciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(Request $request): Response
    {
        $propiedades = Property::query()
            ->visiblePara($request->user())
            ->with(['owners:id,nombre', 'contratoActivo.tenant:id,nombre'])
            ->orderBy('alias')
            ->get()
            ->map(fn (Property $p) => [
                'id' => $p->id,
                'alias' => $p->alias,
                'direccion' => $p->direccionCompleta(),
                'tipo' => $p->tipo->label(),
                'estado' => $p->estado->value,
                'estado_label' => $p->estado->label(),
                'ambientes' => $p->ambientes,
                'superficie_m2' => $p->superficie_m2,
                'propietarios' => $p->owners->map(fn (Owner $o) => [
                    'nombre' => $o->nombre,
                    'porcentaje' => (float) $o->pivot->porcentaje,
                ]),
                'inquilino' => $p->contratoActivo?->tenant->nombre,
                'monto_actual' => $p->contratoActivo?->monto_actual,
            ]);

        return Inertia::render('propiedades/Index', [
            'propiedades' => $propiedades,
        ]);
    }

    public function show(Request $request, Property $property): Response
    {
        // Se consulta con el scope en vez de autorizar después: un propietario que
        // pide una propiedad ajena recibe 404 y no confirma que exista.
        abort_unless(
            Property::query()->visiblePara($request->user())->whereKey($property->id)->exists(),
            404
        );

        $property->load([
            'owners:id,nombre,email,telefono',
            'contracts.tenant:id,nombre',
            'contracts.adjustments',
            'expenses' => fn ($q) => $q->orderByDesc('periodo')->limit(20),
        ]);

        return Inertia::render('propiedades/Show', [
            'propiedad' => [
                'id' => $property->id,
                'alias' => $property->alias,
                'direccion' => $property->direccionCompleta(),
                'tipo' => $property->tipo->label(),
                'estado' => $property->estado->value,
                'estado_label' => $property->estado->label(),
                'ambientes' => $property->ambientes,
                'superficie_m2' => $property->superficie_m2,
                'partida_inmobiliaria' => $property->partida_inmobiliaria,
                'notas' => $property->notas,
                'propietarios' => $property->owners->map(fn (Owner $o) => [
                    'id' => $o->id,
                    'nombre' => $o->nombre,
                    'email' => $o->email,
                    'telefono' => $o->telefono,
                    'porcentaje' => (float) $o->pivot->porcentaje,
                ]),
                'contratos' => $property->contracts->map(fn ($c) => [
                    'id' => $c->id,
                    'inquilino' => $c->tenant->nombre,
                    'desde' => $c->fecha_inicio->format('d/m/Y'),
                    'hasta' => $c->fecha_fin->format('d/m/Y'),
                    'monto_actual' => $c->monto_actual,
                    'estado' => $c->estado->value,
                    'estado_label' => $c->estado->label(),
                    'indice' => $c->indice->labelCorto(),
                ]),
                'gastos' => $property->expenses->map(fn ($g) => [
                    'id' => $g->id,
                    'descripcion' => $g->descripcion ?: $g->categoria->label(),
                    'categoria' => $g->categoria->label(),
                    'periodo' => $g->periodo->format('m/Y'),
                    'monto' => $g->monto,
                    'a_cargo_de' => $g->a_cargo_de->label(),
                    'pagado' => $g->pagado,
                ]),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('propiedades/Form', $this->datosDelFormulario());
    }

    public function store(PropertyRequest $request): RedirectResponse
    {
        $property = DB::transaction(function () use ($request) {
            $property = Property::query()->create($request->safe()->except('propietarios'));
            $this->sincronizarPropietarios($property, $request->input('propietarios', []));

            return $property;
        });

        return to_route('propiedades.show', $property)
            ->with('success', 'Propiedad creada.');
    }

    public function edit(Property $property): Response
    {
        $property->load('owners:id,nombre');

        return Inertia::render('propiedades/Form', [
            ...$this->datosDelFormulario(),
            'propiedad' => [
                ...$property->only([
                    'id', 'alias', 'calle', 'numero', 'piso', 'depto', 'localidad',
                    'provincia', 'codigo_postal', 'ambientes', 'superficie_m2',
                    'partida_inmobiliaria', 'notas',
                ]),
                'tipo' => $property->tipo->value,
                'estado' => $property->estado->value,
                'propietarios' => $property->owners->map(fn (Owner $o) => [
                    'owner_id' => $o->id,
                    'porcentaje' => (float) $o->pivot->porcentaje,
                ])->values(),
            ],
        ]);
    }

    public function update(PropertyRequest $request, Property $property): RedirectResponse
    {
        DB::transaction(function () use ($request, $property) {
            $property->update($request->safe()->except('propietarios'));
            $this->sincronizarPropietarios($property, $request->input('propietarios', []));
        });

        return to_route('propiedades.show', $property)
            ->with('success', 'Propiedad actualizada.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        if ($property->contracts()->exists()) {
            return back()->with('error', 'No se puede borrar: la propiedad tiene contratos cargados.');
        }

        $property->delete();

        return to_route('propiedades.index')->with('success', 'Propiedad eliminada.');
    }

    /** @param  list<array{owner_id: int, porcentaje: float|string}>  $propietarios */
    private function sincronizarPropietarios(Property $property, array $propietarios): void
    {
        $property->owners()->sync(
            collect($propietarios)
                ->mapWithKeys(fn (array $p) => [
                    $p['owner_id'] => ['porcentaje' => $p['porcentaje']],
                ])
                ->all()
        );
    }

    /** @return array<string, mixed> */
    private function datosDelFormulario(): array
    {
        return [
            'tipos' => Opciones::de(TipoPropiedad::class),
            'estados' => Opciones::de(EstadoPropiedad::class),
            'propietariosDisponibles' => Owner::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
        ];
    }
}
