<?php

namespace App\Models;

use App\Enums\MedioPago;
use Carbon\CarbonInterface;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un pago del inquilino contra un cargo de alquiler. Puede haber varios por
 * cargo, lo que permite pagos parciales.
 *
 * @property int $id
 * @property int $rent_charge_id
 * @property CarbonInterface $fecha
 * @property string $monto
 * @property MedioPago $medio
 * @property string|null $referencia
 * @property-read RentCharge $rentCharge
 */
#[Fillable(['rent_charge_id', 'fecha', 'monto', 'medio', 'referencia', 'notas'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
            'medio' => MedioPago::class,
        ];
    }

    /**
     * El estado del cargo se deriva de sus pagos, así que se recalcula solo cada
     * vez que uno se guarda o se borra. Mantenerlo acá evita que un pago cargado
     * desde cualquier lado deje el cargo con un estado mentiroso.
     */
    protected static function booted(): void
    {
        $sincronizar = function (Payment $payment): void {
            $payment->rentCharge()->first()?->actualizarEstado();
        };

        static::saved($sincronizar);
        static::deleted($sincronizar);
    }

    public function rentCharge(): BelongsTo
    {
        return $this->belongsTo(RentCharge::class);
    }

    /** @see Property::scopeVisiblePara() */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->esAdmin()) {
            return $query;
        }

        return $query->whereHas(
            'rentCharge',
            fn (Builder $q) => $q->visiblePara($user)
        );
    }
}
