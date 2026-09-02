<?php

namespace App\Models;

use App\Enums\EstadoContrato;
use App\Enums\Indice;
use Carbon\CarbonInterface;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $property_id
 * @property int $tenant_id
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 * @property string $monto_base
 * @property string $monto_actual
 * @property int $dia_vencimiento
 * @property string|null $deposito
 * @property Indice $indice
 * @property int $frecuencia_meses
 * @property CarbonInterface|null $proximo_ajuste
 * @property int $redondeo
 * @property EstadoContrato $estado
 * @property string|null $notas
 * @property-read Property $property
 * @property-read Tenant $tenant
 */
#[Fillable([
    'property_id', 'tenant_id', 'fecha_inicio', 'fecha_fin', 'monto_base',
    'monto_actual', 'dia_vencimiento', 'deposito', 'indice', 'frecuencia_meses',
    'proximo_ajuste', 'redondeo', 'estado', 'notas',
])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'proximo_ajuste' => 'date',
            'monto_base' => 'decimal:2',
            'monto_actual' => 'decimal:2',
            'deposito' => 'decimal:2',
            'indice' => Indice::class,
            'estado' => EstadoContrato::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(RentAdjustment::class)->orderByDesc('vigencia_desde');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(RentCharge::class)->orderByDesc('periodo');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @see Property::scopeVisiblePara() */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->esAdmin()) {
            return $query;
        }

        return $query->whereHas(
            'property',
            fn (Builder $q) => $q->visiblePara($user)
        );
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', EstadoContrato::Activo);
    }

    /**
     * Contratos cuya fecha de ajuste ya llegó y todavía no tienen una propuesta
     * generada para ese período.
     */
    public function scopeConAjustePendiente(Builder $query, ?CarbonInterface $hasta = null): Builder
    {
        return $query->activos()
            ->whereNotNull('proximo_ajuste')
            ->whereDate('proximo_ajuste', '<=', $hasta ?? today())
            ->where('indice', '!=', Indice::Manual);
    }

    public function estaVigente(): bool
    {
        return $this->estado === EstadoContrato::Activo
            && $this->fecha_fin->isFuture();
    }
}
