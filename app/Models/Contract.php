<?php

namespace App\Models;

use App\Enums\EstadoAjuste;
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
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $property_id
 * @property int $tenant_id
 * @property CarbonInterface $fecha_inicio
 * @property CarbonInterface $fecha_fin
 * @property numeric-string $monto_base
 * @property numeric-string $monto_actual
 * @property int $dia_vencimiento
 * @property numeric-string|null $deposito
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

    /**
     * Al borrar el contrato se van también sus archivos del disco. Las filas de
     * `contract_documents` caen solas por la foreign key.
     */
    protected static function booted(): void
    {
        static::deleting(function (Contract $contract): void {
            Storage::disk('local')->deleteDirectory("contratos/{$contract->id}");
        });
    }

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

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<RentAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(RentAdjustment::class)->orderByDesc('vigencia_desde');
    }

    /** @return HasMany<RentCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(RentCharge::class)->orderByDesc('periodo');
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<ContractDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class)->latest();
    }

    /**
     * @see Property::scopeVisiblePara()
     *
     * @param  Builder<Contract>  $query
     * @return Builder<Contract>
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
     * @param  Builder<Contract>  $query
     * @return Builder<Contract>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', EstadoContrato::Activo);
    }

    /**
     * Contratos cuya fecha de ajuste ya llegó y todavía no tienen una propuesta
     * generada para ese período.
     *
     * @param  Builder<Contract>  $query
     * @return Builder<Contract>
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

    /**
     * El alquiler que estaba vigente durante un período dado, reconstruido a
     * partir del historial de ajustes aplicados (`monto_actual` sólo dice el
     * de HOY). Emitir el cargo de un mes anterior a un ajuste ya aplicado
     * tiene que cobrar lo que regía ese mes, no lo de ahora.
     *
     * @return numeric-string
     */
    public function montoVigenteEn(CarbonInterface $periodo): string
    {
        $periodo = $periodo->copy()->startOfMonth();

        // `adjustments` ya viene ordenado por vigencia_desde descendente, así
        // que el primero que aplica es el más reciente de los que corresponden.
        $ultimoAplicado = $this->adjustments->first(
            fn (RentAdjustment $a) => $a->estado === EstadoAjuste::Aplicado
                && $a->vigencia_desde->lte($periodo)
        );

        return $ultimoAplicado->monto_nuevo ?? $this->monto_base;
    }
}
