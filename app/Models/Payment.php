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
use Illuminate\Support\Facades\Storage;

/**
 * Un pago del inquilino contra un cargo de alquiler. Puede haber varios por
 * cargo, lo que permite pagos parciales.
 *
 * @property int $id
 * @property int $rent_charge_id
 * @property CarbonInterface $fecha
 * @property numeric-string $monto
 * @property MedioPago $medio
 * @property string|null $referencia
 * @property string|null $comprobante_path
 * @property string|null $comprobante_nombre
 * @property string|null $comprobante_mime
 * @property-read RentCharge $rentCharge
 */
#[Fillable(['rent_charge_id', 'fecha', 'monto', 'medio', 'referencia', 'notas', 'comprobante_path', 'comprobante_nombre', 'comprobante_mime'])]
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

        // El comprobante no sobrevive al pago.
        static::deleted(function (Payment $payment): void {
            if ($payment->comprobante_path !== null) {
                Storage::disk('local')->delete($payment->comprobante_path);
            }
        });
    }

    /** @return BelongsTo<RentCharge, $this> */
    public function rentCharge(): BelongsTo
    {
        return $this->belongsTo(RentCharge::class);
    }

    /**
     * @see Property::scopeVisiblePara()
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->esAdmin()) {
            return $query;
        }

        return $query->whereIn(
            'rent_charge_id',
            RentCharge::query()->visiblePara($user)->select('id')
        );
    }
}
