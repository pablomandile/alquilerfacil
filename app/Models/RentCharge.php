<?php

namespace App\Models;

use App\Contracts\Repartible;
use App\Enums\EstadoCargo;
use Carbon\CarbonInterface;
use Database\Factories\RentChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * El alquiler de un mes para un contrato.
 *
 * Se materializa en vez de calcularse al vuelo: congela el monto vigente de ese
 * mes (si después se aplica un ajuste, los cargos ya emitidos no cambian) y es
 * la unidad natural a la que se enganchan los pagos.
 *
 * @property int $id
 * @property int $contract_id
 * @property CarbonInterface $periodo
 * @property numeric-string $monto
 * @property CarbonInterface $vencimiento
 * @property EstadoCargo $estado
 * @property-read Contract $contract
 */
#[Fillable(['contract_id', 'periodo', 'monto', 'vencimiento', 'estado', 'notas'])]
class RentCharge extends Model implements Repartible
{
    /** @use HasFactory<RentChargeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'periodo' => 'date',
            'vencimiento' => 'date',
            'monto' => 'decimal:2',
            'estado' => EstadoCargo::class,
        ];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('fecha');
    }

    /**
     * El reparto del alquiler entre los dueños de la propiedad.
     *
     * @return MorphMany<OwnerShare, covariant Model>
     */
    public function shares(): MorphMany
    {
        return $this->morphMany(OwnerShare::class, 'shareable');
    }

    public function propiedadDelReparto(): Property
    {
        return $this->contract->property;
    }

    /** @return numeric-string */
    public function montoARepartir(): string
    {
        return $this->monto;
    }

    /**
     * @see Property::scopeVisiblePara()
     *
     * @param  Builder<RentCharge>  $query
     * @return Builder<RentCharge>
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
     * @param  Builder<RentCharge>  $query
     * @return Builder<RentCharge>
     */
    public function scopeDelPeriodo(Builder $query, CarbonInterface $periodo): Builder
    {
        return $query->whereDate('periodo', $periodo->copy()->startOfMonth());
    }

    /** @return numeric-string */
    public function totalPagado(): string
    {
        return bcadd((string) $this->payments()->sum('monto'), '0', 2);
    }

    /** @return numeric-string */
    public function saldo(): string
    {
        return bcsub($this->monto, $this->totalPagado(), 2);
    }

    /**
     * Recalcula el estado a partir de los pagos registrados y lo guarda.
     *
     * Se llama después de agregar o borrar un pago. `vencido` sólo aplica cuando
     * no se pagó nada: si hay un pago parcial, el estado informativo útil es
     * `parcial`, que ya deja ver que falta plata.
     */
    public function actualizarEstado(): void
    {
        $pagado = $this->totalPagado();
        $estado = match (true) {
            bccomp($pagado, $this->monto, 2) >= 0 => EstadoCargo::Pagado,
            bccomp($pagado, '0', 2) > 0 => EstadoCargo::Parcial,
            $this->vencimiento->isPast() => EstadoCargo::Vencido,
            default => EstadoCargo::Pendiente,
        };

        if ($this->estado !== $estado) {
            $this->update(['estado' => $estado]);
        }
    }
}
