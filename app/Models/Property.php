<?php

namespace App\Models;

use App\Enums\EstadoContrato;
use App\Enums\EstadoPropiedad;
use App\Enums\TipoPropiedad;
use Carbon\CarbonInterface;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $alias
 * @property TipoPropiedad $tipo
 * @property EstadoPropiedad $estado
 * @property string|null $calle
 * @property string|null $numero
 * @property string|null $piso
 * @property string|null $depto
 * @property string|null $localidad
 * @property string|null $provincia
 * @property string|null $codigo_postal
 * @property int|null $ambientes
 * @property string|null $superficie_m2
 * @property string|null $partida_inmobiliaria
 * @property string|null $notas
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'alias', 'tipo', 'estado', 'calle', 'numero', 'piso', 'depto',
    'localidad', 'provincia', 'codigo_postal', 'ambientes',
    'superficie_m2', 'partida_inmobiliaria', 'notas',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoPropiedad::class,
            'estado' => EstadoPropiedad::class,
            'superficie_m2' => 'decimal:2',
        ];
    }

    /** @return BelongsToMany<Owner, $this, PropertyOwner, 'pivot'> */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class, 'property_owner')
            ->using(PropertyOwner::class)
            ->withPivot('porcentaje')
            ->withTimestamps();
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * El contrato vigente, si hay alguno.
     *
     * @return HasOne<Contract, $this>
     */
    public function contratoActivo(): HasOne
    {
        return $this->hasOne(Contract::class)
            ->where('estado', EstadoContrato::Activo)
            ->latestOfMany('fecha_inicio');
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Limita la consulta a lo que el usuario tiene permitido ver.
     *
     * El admin ve todo. Un propietario ve sólo las propiedades donde figura como
     * dueño. Los demás modelos filtran a través de este scope, para que la regla
     * de visibilidad viva en un solo lugar.
     */
    /**
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->esAdmin()) {
            return $query;
        }

        return $query->whereHas(
            'owners',
            fn ($q) => $q->where('owners.user_id', $user->id)
        );
    }

    public function direccionCompleta(): string
    {
        $calle = trim("{$this->calle} {$this->numero}");
        $unidad = trim(implode(' ', array_filter([
            $this->piso ? "Piso {$this->piso}" : null,
            $this->depto ? "Depto {$this->depto}" : null,
        ])));

        return trim(implode(', ', array_filter([$calle, $unidad, $this->localidad])));
    }

    /** Suma de los porcentajes cargados. Debe dar 100 para ser válida. */
    public function porcentajeTotal(): float
    {
        return (float) $this->owners->sum('pivot.porcentaje');
    }
}
