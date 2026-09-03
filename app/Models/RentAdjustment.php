<?php

namespace App\Models;

use App\Enums\EstadoAjuste;
use App\Enums\Indice;
use Carbon\CarbonInterface;
use Database\Factories\RentAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ajuste del valor del alquiler por índice.
 *
 * Nace siempre como `propuesto`: la app calcula y ofrece, el usuario decide.
 * Guarda los valores del índice usados para que quede auditable aunque después
 * el INDEC revise la serie.
 *
 * @property int $id
 * @property int $contract_id
 * @property CarbonInterface $vigencia_desde
 * @property numeric-string $monto_anterior
 * @property numeric-string $monto_nuevo
 * @property numeric-string $coeficiente
 * @property Indice $indice
 * @property CarbonInterface $periodo_indice_desde
 * @property CarbonInterface $periodo_indice_hasta
 * @property numeric-string $valor_indice_desde
 * @property numeric-string $valor_indice_hasta
 * @property numeric-string $variacion_porcentual
 * @property EstadoAjuste $estado
 * @property CarbonInterface|null $aplicado_at
 * @property-read Contract $contract
 */
#[Fillable([
    'contract_id', 'vigencia_desde', 'monto_anterior', 'monto_nuevo', 'coeficiente',
    'indice', 'periodo_indice_desde', 'periodo_indice_hasta', 'valor_indice_desde',
    'valor_indice_hasta', 'variacion_porcentual', 'estado', 'aplicado_at', 'notas',
])]
class RentAdjustment extends Model
{
    /** @use HasFactory<RentAdjustmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'vigencia_desde' => 'date',
            'periodo_indice_desde' => 'date',
            'periodo_indice_hasta' => 'date',
            'monto_anterior' => 'decimal:2',
            'monto_nuevo' => 'decimal:2',
            'coeficiente' => 'decimal:8',
            'valor_indice_desde' => 'decimal:8',
            'valor_indice_hasta' => 'decimal:8',
            'variacion_porcentual' => 'decimal:4',
            'indice' => Indice::class,
            'estado' => EstadoAjuste::class,
            'aplicado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @see Property::scopeVisiblePara()
     *
     * @param  Builder<RentAdjustment>  $query
     * @return Builder<RentAdjustment>
     */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->esAdmin()) {
            return $query;
        }

        return $query->whereIn(
            'contract_id',
            Contract::query()->visiblePara($user)->select('id')
        );
    }

    /**
     * @param  Builder<RentAdjustment>  $query
     * @return Builder<RentAdjustment>
     */
    public function scopePropuestos(Builder $query): Builder
    {
        return $query->where('estado', EstadoAjuste::Propuesto);
    }

    public function estaPropuesto(): bool
    {
        return $this->estado === EstadoAjuste::Propuesto;
    }

    /**
     * Diferencia en pesos entre el monto nuevo y el anterior.
     *
     * @return numeric-string
     */
    public function diferencia(): string
    {
        return bcsub($this->monto_nuevo, $this->monto_anterior, 2);
    }
}
