<?php

namespace App\Http\Controllers;

use App\Enums\EstadoContrato;
use App\Enums\Indice;
use App\Enums\TipoDocumentoContrato;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\Property;
use App\Models\Tenant;
use App\Services\Ajustes\CalculadorDeAjuste;
use App\Services\Ajustes\PropuestaDeAjuste;
use App\Support\Opciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        $contratos = Contract::query()
            ->visiblePara($request->user())
            ->with(['property:id,alias', 'tenant:id,nombre'])
            ->orderByDesc('fecha_inicio')
            ->get()
            ->map(fn (Contract $c) => [
                'id' => $c->id,
                'propiedad' => $c->property->alias,
                'propiedad_id' => $c->property_id,
                'inquilino' => $c->tenant->nombre,
                'desde' => $c->fecha_inicio->format('d/m/Y'),
                'hasta' => $c->fecha_fin->format('d/m/Y'),
                'monto_actual' => $c->monto_actual,
                'indice' => $c->indice->labelCorto(),
                'frecuencia_meses' => $c->frecuencia_meses,
                'proximo_ajuste' => $c->proximo_ajuste?->format('d/m/Y'),
                'ajuste_vencido' => $c->proximo_ajuste?->isPast() ?? false,
                'estado' => $c->estado->value,
                'estado_label' => $c->estado->label(),
            ]);

        return Inertia::render('contratos/Index', [
            'contratos' => $contratos,
        ]);
    }

    public function show(Request $request, Contract $contract, CalculadorDeAjuste $calculador): Response
    {
        abort_unless(
            Contract::query()->visiblePara($request->user())->whereKey($contract->id)->exists(),
            404
        );

        $contract->load([
            'property:id,alias',
            'tenant',
            'adjustments',
            'charges.payments',
            'documents.uploader:id,name',
        ]);

        // Se calcula al vuelo para mostrar cuánto daría hoy el próximo ajuste,
        // aunque todavía no esté en fecha ni se haya propuesto formalmente.
        $proyeccion = $contract->indice->seSincroniza()
            ? $calculador->calcular($contract)
            : null;

        return Inertia::render('contratos/Show', [
            'contrato' => [
                'id' => $contract->id,
                'propiedad' => $contract->property->alias,
                'propiedad_id' => $contract->property_id,
                'inquilino' => $contract->tenant->only(['id', 'nombre', 'email', 'telefono', 'documento']),
                'desde' => $contract->fecha_inicio->format('d/m/Y'),
                'hasta' => $contract->fecha_fin->format('d/m/Y'),
                'monto_base' => $contract->monto_base,
                'monto_actual' => $contract->monto_actual,
                'deposito' => $contract->deposito,
                'dia_vencimiento' => $contract->dia_vencimiento,
                'indice' => $contract->indice->label(),
                'frecuencia_meses' => $contract->frecuencia_meses,
                'proximo_ajuste' => $contract->proximo_ajuste?->format('d/m/Y'),
                'estado' => $contract->estado->value,
                'estado_label' => $contract->estado->label(),
                'notas' => $contract->notas,
                'ajustes' => $contract->adjustments->map(fn ($a) => [
                    'id' => $a->id,
                    'vigencia' => $a->vigencia_desde->format('d/m/Y'),
                    'monto_anterior' => $a->monto_anterior,
                    'monto_nuevo' => $a->monto_nuevo,
                    'variacion' => (float) $a->variacion_porcentual,
                    'indice' => $a->indice->labelCorto(),
                    'estado' => $a->estado->value,
                    'estado_label' => $a->estado->label(),
                ]),
                'cargos' => $contract->charges->map(fn ($c) => [
                    'id' => $c->id,
                    'periodo' => $c->periodo->translatedFormat('F Y'),
                    'monto' => $c->monto,
                    'pagado' => $c->totalPagado(),
                    'saldo' => $c->saldo(),
                    'vencimiento' => $c->vencimiento->format('d/m/Y'),
                    'estado' => $c->estado->value,
                    'estado_label' => $c->estado->label(),
                ]),
                'documentos' => $contract->documents->map(fn (ContractDocument $d) => [
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
            ],
            'proyeccion' => $proyeccion instanceof PropuestaDeAjuste
                ? [
                    'disponible' => true,
                    'vigencia' => $proyeccion->vigenciaDesde->format('d/m/Y'),
                    'monto_nuevo' => $proyeccion->montoNuevo,
                    'variacion' => (float) $proyeccion->variacionPorcentual,
                    'ventana' => $proyeccion->periodoIndiceDesde->format('m/Y').' a '.$proyeccion->periodoIndiceHasta->format('m/Y'),
                ]
                : ($proyeccion !== null
                    ? ['disponible' => false, 'motivo' => $proyeccion->motivo()]
                    : null),
            'tiposDocumento' => Opciones::de(TipoDocumentoContrato::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Contract::class);

        return Inertia::render('contratos/Form', $this->datosDelFormulario($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validado($request);
        $this->authorize('create', [Contract::class, Property::findOrFail($datos['property_id'])]);

        // El alquiler arranca valiendo lo pactado; el ajuste lo mueve después.
        $datos['monto_actual'] = $datos['monto_base'];

        $contrato = Contract::query()->create($datos);

        return to_route('contratos.show', $contrato)->with('success', 'Contrato creado.');
    }

    public function edit(Request $request, Contract $contract): Response
    {
        $this->authorize('update', $contract);

        return Inertia::render('contratos/Form', [
            ...$this->datosDelFormulario($request),
            'contrato' => [
                ...$contract->only([
                    'id', 'property_id', 'tenant_id', 'monto_base', 'monto_actual',
                    'dia_vencimiento', 'deposito', 'frecuencia_meses', 'redondeo', 'notas',
                ]),
                'fecha_inicio' => $contract->fecha_inicio->toDateString(),
                'fecha_fin' => $contract->fecha_fin->toDateString(),
                'proximo_ajuste' => $contract->proximo_ajuste?->toDateString(),
                'indice' => $contract->indice->value,
                'estado' => $contract->estado->value,
            ],
        ]);
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $datos = $this->validado($request, editando: true);

        if ((int) $datos['property_id'] !== $contract->property_id) {
            $this->authorize('create', [Contract::class, Property::findOrFail($datos['property_id'])]);
        }

        $contract->update($datos);

        return to_route('contratos.show', $contract)->with('success', 'Contrato actualizado.');
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);

        if ($contract->charges()->exists()) {
            return back()->with('error', 'No se puede borrar: el contrato tiene cargos emitidos.');
        }

        $contract->delete();

        return to_route('contratos.index')->with('success', 'Contrato eliminado.');
    }

    /** @return array<string, mixed> */
    private function validado(Request $request, bool $editando = false): array
    {
        return $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'monto_base' => ['required', 'numeric', 'min:0'],
            'monto_actual' => [$editando ? 'required' : 'nullable', 'numeric', 'min:0'],
            'dia_vencimiento' => ['required', 'integer', 'min:1', 'max:31'],
            'deposito' => ['nullable', 'numeric', 'min:0'],
            'indice' => ['required', Rule::enum(Indice::class)],
            'frecuencia_meses' => ['required', 'integer', 'min:1', 'max:24'],
            'proximo_ajuste' => ['nullable', 'date'],
            'redondeo' => ['required', 'integer', Rule::in([0, 100, 1000])],
            'estado' => ['required', Rule::enum(EstadoContrato::class)],
            'notas' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function datosDelFormulario(Request $request): array
    {
        return [
            'propiedades' => Property::query()
                ->visiblePara($request->user())
                ->orderBy('alias')
                ->get(['id', 'alias']),
            'inquilinos' => Tenant::query()
                ->visiblePara($request->user())
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'indices' => Opciones::de(Indice::class),
            'estados' => Opciones::de(EstadoContrato::class),
            'opcionesRedondeo' => [
                ['value' => 0, 'label' => 'Sin redondear'],
                ['value' => 100, 'label' => 'Al centenar más cercano'],
                ['value' => 1000, 'label' => 'Al millar más cercano'],
            ],
        ];
    }
}
