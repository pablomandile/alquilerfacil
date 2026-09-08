<?php

namespace App\Models;

use App\Contracts\Repartible;
use App\Enums\ACargoDe;
use App\Enums\CategoriaGasto;
use App\Enums\TipoGasto;
use Carbon\CarbonInterface;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $property_id
 * @property int|null $contract_id
 * @property TipoGasto $tipo
 * @property CategoriaGasto $categoria
 * @property string|null $descripcion
 * @property CarbonInterface $periodo
 * @property numeric-string $monto
 * @property CarbonInterface|null $vencimiento
 * @property ACargoDe $a_cargo_de
 * @property bool $pagado
 * @property CarbonInterface|null $fecha_pago
 * @property-read Property $property
 */
#[Fillable([
    'property_id', 'contract_id', 'tipo', 'categoria', 'descripcion', 'periodo',
    'monto', 'vencimiento', 'a_cargo_de', 'pagado', 'fecha_pago', 'notas',
])]
class Expense extends Model implements Repartible
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * Al borrar el gasto se van sus archivos del disco y su reparto entre
     * dueños (OwnerShare es polimórfico, sin foreign key que lo arrastre). Las
     * filas de expense_documents caen solas por la foreign key.
     */
    protected static function booted(): void
    {
        static::deleting(function (Expense $expense): void {
            $expense->shares()->delete();
            Storage::disk('local')->deleteDirectory("gastos/{$expense->id}");
        });
    }

    protected function casts(): array
    {
        return [
            'periodo' => 'date',
            'vencimiento' => 'date',
            'fecha_pago' => 'date',
            'monto' => 'decimal:2',
            'pagado' => 'boolean',
            'tipo' => TipoGasto::class,
            'categoria' => CategoriaGasto::class,
            'a_cargo_de' => ACargoDe::class,
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasMany<ExpenseDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ExpenseDocument::class)->latest();
    }

    /**
     * El reparto entre dueños. Sólo existe si el gasto está a cargo de ellos.
     *
     * @return MorphMany<OwnerShare, covariant Model>
     */
    public function shares(): MorphMany
    {
        return $this->morphMany(OwnerShare::class, 'shareable');
    }

    public function propiedadDelReparto(): Property
    {
        return $this->property;
    }

    /** @return numeric-string */
    public function montoARepartir(): string
    {
        return $this->monto;
    }

    /**
     * @see Property::scopeVisiblePara()
     *
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->esAdmin()) {
            return $query;
        }

        return $query->whereIn(
            'property_id',
            Property::query()->visiblePara($user)->select('id')
        );
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopeImpagos(Builder $query): Builder
    {
        return $query->where('pagado', false);
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopeACargoDeLosPropietarios(Builder $query): Builder
    {
        return $query->where('a_cargo_de', ACargoDe::Propietarios);
    }

    public function seRepartEntrePropietarios(): bool
    {
        return $this->a_cargo_de->seReparte();
    }

    public function estaVencido(): bool
    {
        return ! $this->pagado
            && $this->vencimiento !== null
            && $this->vencimiento->isPast();
    }
}
