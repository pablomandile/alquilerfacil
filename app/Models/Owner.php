<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use Carbon\CarbonInterface;
use Database\Factories\OwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Propietario de una o más propiedades.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $nombre
 * @property TipoDocumento|null $tipo_documento
 * @property string|null $documento
 * @property string|null $email
 * @property string|null $telefono
 * @property string|null $cbu
 * @property string|null $alias_cbu
 * @property string|null $notas
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read PropertyOwner $pivot  Sólo cuando se lo carga a través de Property::owners().
 */
#[Fillable([
    'user_id', 'nombre', 'tipo_documento', 'documento',
    'email', 'telefono', 'cbu', 'alias_cbu', 'notas',
])]
class Owner extends Model
{
    /** @use HasFactory<OwnerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
        ];
    }

    /**
     * Cuenta de acceso, si este propietario entra a la app. Puede no tener.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Property, $this, PropertyOwner, 'pivot'> */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_owner')
            ->using(PropertyOwner::class)
            ->withPivot('porcentaje')
            ->withTimestamps();
    }

    /** @return HasMany<OwnerShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(OwnerShare::class);
    }

    public function tieneAcceso(): bool
    {
        return $this->user_id !== null;
    }
}
