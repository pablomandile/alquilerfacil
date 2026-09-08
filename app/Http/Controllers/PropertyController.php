<?php

namespace App\Http\Controllers;

use App\Enums\ACargoDe;
use App\Enums\CategoriaTemaAdmin;
use App\Enums\EstadoPropiedad;
use App\Enums\TipoDocumentoPropiedad;
use App\Enums\TipoGasto;
use App\Enums\TipoPropiedad;
use App\Http\Requests\PropertyRequest;
use App\Models\AdminThread;
use App\Models\AdminThreadAttachment;
use App\Models\AdminThreadEntry;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\Property;
use App\Models\PropertyDocument;
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
            'contracts.charges.payments',
            'expenses' => fn ($q) => $q->orderByDesc('periodo')->limit(20),
            'documents.uploader:id,name',
            'adminThreads.entries.attachments',
            'adminThreads.entries.registrador:id,name',
        ]);

        // Totalización del alquiler de la propiedad: lo facturado y lo cobrado en
        // el contrato vigente y en los anteriores, y el neto contra los gastos
        // extraordinarios que absorben los propietarios.
        $totalesPorContrato = $property->contracts
            ->sortByDesc('fecha_inicio')
            ->values()
            ->map(function (Contract $contrato) {
                $facturado = '0';
                $cobrado = '0';

                foreach ($contrato->charges as $cargo) {
                    $facturado = bcadd($facturado, $cargo->monto, 2);

                    foreach ($cargo->payments as $pago) {
                        $cobrado = bcadd($cobrado, $pago->monto, 2);
                    }
                }

                return [
                    'id' => $contrato->id,
                    'inquilino' => $contrato->tenant->nombre,
                    'estado' => $contrato->estado->value,
                    'estado_label' => $contrato->estado->label(),
                    'facturado' => bcadd($facturado, '0', 2),
                    'cobrado' => bcadd($cobrado, '0', 2),
                ];
            });

        $facturadoTotal = bcadd($totalesPorContrato->reduce(
            fn (string $acc, array $c) => bcadd($acc, $c['facturado'], 2),
            '0'
        ), '0', 2);
        $cobradoTotal = bcadd($totalesPorContrato->reduce(
            fn (string $acc, array $c) => bcadd($acc, $c['cobrado'], 2),
            '0'
        ), '0', 2);

        $gastosExtraordinarios = bcadd((string) $property->expenses()
            ->where('tipo', TipoGasto::Extraordinario)
            ->where('a_cargo_de', ACargoDe::Propietarios)
            ->sum('monto'), '0', 2);

        // Cuadro del mes para pasarle al inquilino: el alquiler más los gastos a
        // su cargo que vencen en el mes en curso. Sólo si hay contrato vigente.
        $contratoActivo = $property->contratoActivo()
            ->with('tenant:id,nombre,telefono')
            ->first();

        $mensajeInquilino = $contratoActivo?->tenant === null
            ? null
            : $this->mensajeParaInquilino($contratoActivo);

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
                'documentos' => $property->documents->map(fn (PropertyDocument $d) => [
                    'id' => $d->id,
                    'tipo' => $d->tipo->value,
                    'tipo_label' => $d->tipo->label(),
                    'nota' => $d->nota,
                    'nombre' => $d->nombre_original,
                    'tamano' => $d->tamano,
                    'mime' => $d->mime,
                    'subido_por' => $d->uploader?->name,
                    'fecha' => $d->created_at?->format('d/m/Y'),
                ]),
                'administracion' => $property->adminThreads->map(fn (AdminThread $t) => [
                    'id' => $t->id,
                    'titulo' => $t->titulo,
                    'categoria' => $t->categoria->value,
                    'categoria_label' => $t->categoria->label(),
                    'estado' => $t->estado->value,
                    'estado_label' => $t->estado->label(),
                    'creado' => $t->created_at?->format('d/m/Y'),
                    'entradas' => $t->entries->map(fn (AdminThreadEntry $e) => [
                        'id' => $e->id,
                        'fecha' => $e->fecha->format('d/m/Y'),
                        'fecha_iso' => $e->fecha->toDateString(),
                        'detalle' => $e->detalle,
                        'registrado_por' => $e->registrador?->name,
                        'adjuntos' => $e->attachments->map(fn (AdminThreadAttachment $a) => [
                            'id' => $a->id,
                            'nombre' => $a->nombre_original,
                            'tamano' => $a->tamano,
                            'mime' => $a->mime,
                        ])->all(),
                    ])->all(),
                ]),
                'totales' => [
                    'por_contrato' => $totalesPorContrato,
                    'facturado' => $facturadoTotal,
                    'cobrado' => $cobradoTotal,
                    'gastos_extraordinarios' => $gastosExtraordinarios,
                    'neto' => bcsub($cobradoTotal, $gastosExtraordinarios, 2),
                ],
            ],
            'tiposDocumento' => Opciones::de(TipoDocumentoPropiedad::class),
            'categoriasTemaAdmin' => Opciones::de(CategoriaTemaAdmin::class),
            'mensajeInquilino' => $mensajeInquilino,
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

    /**
     * El alquiler del mes más los gastos a cargo del inquilino que vencen en el
     * mes en curso: lo que se le informa para que pague.
     *
     * @return array<string, mixed>
     */
    private function mensajeParaInquilino(Contract $contrato): array
    {
        $mes = now()->startOfMonth();

        // El cargo del mes si ya está emitido: tiene el monto congelado, su
        // vencimiento y lo que falta pagar. Si no, se usa el alquiler actual y
        // el día de vencimiento del contrato.
        $cargo = $contrato->charges()->delPeriodo($mes)->first();

        if ($cargo !== null) {
            $alquiler = [
                'concepto' => 'Alquiler '.$mes->translatedFormat('F'),
                'monto' => $cargo->saldo(),
                'vencimiento' => $cargo->vencimiento->format('d/m/Y'),
                'pagado' => bccomp($cargo->saldo(), '0', 2) <= 0,
            ];
        } else {
            $dia = min($contrato->dia_vencimiento, $mes->daysInMonth);

            $alquiler = [
                'concepto' => 'Alquiler '.$mes->translatedFormat('F'),
                'monto' => $contrato->monto_actual,
                'vencimiento' => $mes->copy()->day($dia)->format('d/m/Y'),
                'pagado' => false,
            ];
        }

        $gastos = $contrato->property->expenses()
            ->where('a_cargo_de', ACargoDe::Inquilino)
            ->whereNotNull('vencimiento')
            ->whereMonth('vencimiento', $mes->month)
            ->whereYear('vencimiento', $mes->year)
            ->orderBy('vencimiento')
            ->get()
            ->map(fn (Expense $g) => [
                'concepto' => $g->descripcion ?: $g->categoria->label(),
                'monto' => $g->monto,
                'vencimiento' => $g->vencimiento?->format('d/m/Y'),
                'pagado' => $g->pagado,
            ]);

        return [
            'inquilino' => $contrato->tenant->nombre,
            'telefono' => $contrato->tenant->telefono,
            'mes' => now()->translatedFormat('F \d\e Y'),
            'alquiler' => $alquiler,
            'gastos' => $gastos,
        ];
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
