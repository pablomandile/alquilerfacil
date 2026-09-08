<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TenantMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El aviso mensual que se le pasa al inquilino (alquiler + gastos) quedó
 * marcado como enviado. Sólo hay fila cuando está enviado: sin fila, pendiente.
 *
 * @property int $id
 * @property int $property_id
 * @property CarbonInterface $periodo
 * @property CarbonInterface $enviado_at
 * @property int|null $enviado_por
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Property $property
 * @property-read User|null $enviadoPor
 */
#[Fillable(['property_id', 'periodo', 'enviado_at', 'enviado_por'])]
class TenantMessage extends Model
{
    /** @use HasFactory<TenantMessageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'periodo' => 'date',
            'enviado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    /**
     * @param  Builder<TenantMessage>  $query
     * @return Builder<TenantMessage>
     */
    public function scopeDelPeriodo(Builder $query, CarbonInterface $periodo): Builder
    {
        return $query->whereDate('periodo', $periodo->copy()->startOfMonth());
    }
}
