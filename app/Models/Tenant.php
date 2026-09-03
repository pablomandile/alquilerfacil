<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use Carbon\CarbonInterface;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inquilino. No tiene acceso a la app: es sólo un registro de datos.
 *
 * @property int $id
 * @property string $nombre
 * @property TipoDocumento|null $tipo_documento
 * @property string|null $documento
 * @property string|null $email
 * @property string|null $telefono
 * @property string|null $notas
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['nombre', 'tipo_documento', 'documento', 'email', 'telefono', 'notas'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
        ];
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * @see Property::scopeVisiblePara()
     *
     * @param  Builder<Tenant>  $query
     * @return Builder<Tenant>
     */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->esAdmin()) {
            return $query;
        }

        return $query->whereIn(
            'id',
            Contract::query()->visiblePara($user)->select('tenant_id')
        );
    }
}
