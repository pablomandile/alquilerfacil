<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * La parte de un monto que le corresponde a un propietario.
 *
 * Es polimórfica porque el negocio reparte dos cosas con la misma regla: el
 * alquiler (shareable = RentCharge) y los gastos a cargo de los propietarios
 * (shareable = Expense).
 *
 * @property int $id
 * @property string $shareable_type
 * @property int $shareable_id
 * @property int $owner_id
 * @property string $porcentaje
 * @property string $monto
 * @property-read Owner $owner
 */
#[Fillable(['shareable_type', 'shareable_id', 'owner_id', 'porcentaje', 'monto'])]
class OwnerShare extends Model
{
    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
            'monto' => 'decimal:2',
        ];
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function scopeDelPropietario(Builder $query, Owner $owner): Builder
    {
        return $query->where('owner_id', $owner->id);
    }
}
