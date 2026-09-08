<?php

namespace App\Http\Controllers;

use App\Enums\ACargoDe;
use App\Enums\CategoriaGasto;
use App\Enums\TipoDocumentoGasto;
use App\Enums\TipoGasto;
use App\Exceptions\RepartoInvalidoException;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\Property;
use App\Services\Repartos\RepartidorEntreDuenos;
use App\Support\Opciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(private readonly RepartidorEntreDuenos $repartidor) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'property_id' => ['nullable', 'integer'],
            'tipo' => ['nullable', 'string'],
            'pagado' => ['nullable', 'in:si,no'],
        ]);

        $gastos = Expense::query()
            ->visiblePara($request->user())
            ->when($filtros['property_id'] ?? null, fn ($q, $id) => $q->where('property_id', $id))
            ->when($filtros['tipo'] ?? null, fn ($q, $t) => $q->where('tipo', $t))
            ->when(
                $filtros['pagado'] ?? null,
                fn ($q, $p) => $q->where('pagado', $p === 'si')
            )
            ->with(['property:id,alias', 'shares.owner:id,nombre', 'documents'])
            ->orderByDesc('periodo')
            ->orderBy('vencimiento')
            ->get()
            ->map(fn (Expense $g) => [
                'id' => $g->id,
                'propiedad' => $g->property->alias,
                'propiedad_id' => $g->property_id,
                'tipo' => $g->tipo->label(),
                'categoria' => $g->categoria->label(),
                'descripcion' => $g->descripcion,
                'periodo' => $g->periodo->format('m/Y'),
                'monto' => $g->monto,
                'vencimiento' => $g->vencimiento?->format('d/m/Y'),
                'a_cargo_de' => $g->a_cargo_de->value,
                'a_cargo_de_label' => $g->a_cargo_de->label(),
                'pagado' => $g->pagado,
                'vencido' => $g->estaVencido(),
                'reparto' => $g->shares->map(fn ($s) => [
                    'nombre' => $s->owner->nombre,
                    'porcentaje' => (float) $s->porcentaje,
                    'monto' => $s->monto,
                ]),
                'documentos' => $g->documents->map(fn (ExpenseDocument $d) => [
                    'id' => $d->id,
                    'tipo_label' => $d->tipo->label(),
                    'nombre' => $d->nombre_original,
                    'mime' => $d->mime,
                ]),
            ]);

        return Inertia::render('gastos/Index', [
            'gastos' => $gastos,
            'filtros' => $filtros,
            'propiedades' => Property::query()
                ->visiblePara($request->user())
                ->orderBy('alias')
                ->get(['id', 'alias']),
            'tipos' => Opciones::de(TipoGasto::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Expense::class);

        return Inertia::render('gastos/Form', $this->datosDelFormulario($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validado($request);
        $this->authorize('create', [Expense::class, Property::findOrFail($datos['property_id'])]);

        $gasto = Expense::query()->create(Arr::except($datos, ['factura', 'comprobante']));
        $this->guardarDocumentos($request, $gasto);

        return $this->repartirYVolver($gasto, 'Gasto cargado.');
    }

    public function edit(Request $request, Expense $expense): Response
    {
        $this->authorize('update', $expense);

        $expense->load('documents');

        return Inertia::render('gastos/Form', [
            ...$this->datosDelFormulario($request),
            'gasto' => [
                ...$expense->only(['id', 'property_id', 'contract_id', 'descripcion', 'monto', 'notas', 'pagado']),
                'tipo' => $expense->tipo->value,
                'categoria' => $expense->categoria->value,
                'a_cargo_de' => $expense->a_cargo_de->value,
                'periodo' => $expense->periodo->toDateString(),
                'vencimiento' => $expense->vencimiento?->toDateString(),
                'fecha_pago' => $expense->fecha_pago?->toDateString(),
                'documentos' => $expense->documents->map(fn (ExpenseDocument $d) => [
                    'id' => $d->id,
                    'tipo' => $d->tipo->value,
                    'tipo_label' => $d->tipo->label(),
                    'nombre' => $d->nombre_original,
                    'tamano' => $d->tamano,
                    'mime' => $d->mime,
                    'fecha' => $d->created_at?->format('d/m/Y'),
                ]),
            ],
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $datos = $this->validado($request);

        // Si lo mueve a otra propiedad, tiene que poder gestionar también esa.
        if ((int) $datos['property_id'] !== $expense->property_id) {
            $this->authorize('create', [Expense::class, Property::findOrFail($datos['property_id'])]);
        }

        $expense->update(Arr::except($datos, ['factura', 'comprobante']));
        $this->guardarDocumentos($request, $expense);

        return $this->repartirYVolver($expense->fresh(), 'Gasto actualizado.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return to_route('gastos.index')->with('success', 'Gasto eliminado.');
    }

    /**
     * Los gastos a cargo de los propietarios se reparten entre ellos; los del
     * inquilino no, y si el gasto cambió de mano hay que borrar el reparto viejo.
     */
    private function repartirYVolver(Expense $expense, string $mensaje): RedirectResponse
    {
        if (! $expense->seRepartEntrePropietarios()) {
            $expense->shares()->delete();

            return to_route('gastos.index')->with('success', $mensaje);
        }

        try {
            $this->repartidor->repartir($expense);
        } catch (RepartoInvalidoException $e) {
            return to_route('gastos.index')
                ->with('error', $mensaje.' Pero no se pudo repartir: '.$e->getMessage());
        }

        $detalle = $expense->a_cargo_de === ACargoDe::Mitades
            ? ' La mitad se repartió entre los propietarios.'
            : ' Repartido entre los propietarios.';

        return to_route('gastos.index')->with('success', $mensaje.$detalle);
    }

    /** @return array<string, mixed> */
    private function validado(Request $request): array
    {
        return $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'tipo' => ['required', Rule::enum(TipoGasto::class)],
            'categoria' => ['required', Rule::enum(CategoriaGasto::class)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'periodo' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0'],
            'vencimiento' => ['nullable', 'date'],
            'a_cargo_de' => ['required', Rule::enum(ACargoDe::class)],
            'pagado' => ['boolean'],
            'fecha_pago' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:5000'],
            // La factura / expensa del período y el comprobante de pago. Por
            // extensión y no por MIME (los .docx dan falso negativo).
            'factura' => ['nullable', 'file', 'max:10240', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx'],
            'comprobante' => ['nullable', 'file', 'max:10240', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);
    }

    /**
     * Guarda la factura y/o el comprobante que vengan en el request, cada uno
     * con su tipo. Se llama después de crear/actualizar el gasto, cuando ya
     * tiene id.
     */
    private function guardarDocumentos(Request $request, Expense $expense): void
    {
        $tipoPorCampo = [
            'factura' => TipoDocumentoGasto::Factura,
            'comprobante' => TipoDocumentoGasto::Comprobante,
        ];

        foreach ($tipoPorCampo as $campo => $tipo) {
            $archivo = $request->file($campo);

            if (! $archivo instanceof UploadedFile) {
                continue;
            }

            $path = $archivo->storeAs(
                "gastos/{$expense->id}",
                Str::ulid().'.'.strtolower($archivo->getClientOriginalExtension()),
                'local',
            );

            if ($path === false) {
                continue;
            }

            $expense->documents()->create([
                'tipo' => $tipo,
                'nombre_original' => $archivo->getClientOriginalName(),
                'path' => $path,
                'mime' => $archivo->getMimeType() ?? $archivo->getClientMimeType(),
                'tamano' => $archivo->getSize() ?: 0,
                'subido_por' => $request->user()?->id,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function datosDelFormulario(Request $request): array
    {
        return [
            'propiedades' => Property::query()
                ->visiblePara($request->user())
                ->orderBy('alias')
                ->get(['id', 'alias']),
            'contratos' => Contract::query()
                ->visiblePara($request->user())
                ->activos()
                ->with('property:id,alias')
                ->get()
                ->map(fn (Contract $c) => [
                    'id' => $c->id,
                    'property_id' => $c->property_id,
                    'label' => $c->property->alias,
                ]),
            'tipos' => Opciones::de(TipoGasto::class),
            'categorias' => Opciones::de(CategoriaGasto::class),
            'aCargoDe' => Opciones::de(ACargoDe::class),
        ];
    }
}
