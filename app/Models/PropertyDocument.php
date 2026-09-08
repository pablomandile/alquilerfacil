<?php

namespace App\Models;

use App\Enums\TipoDocumentoPropiedad;
use Carbon\CarbonInterface;
use Database\Factories\PropertyDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Un archivo adjunto a una propiedad: la escritura, el reglamento de
 * copropiedad, los planos, un impuesto, etc. Vive en el disco privado `local` y
 * se descarga por el PropertyDocumentController, nunca por URL directa.
 *
 * @property int $id
 * @property int $property_id
 * @property TipoDocumentoPropiedad $tipo
 * @property string|null $nota
 * @property string $nombre_original
 * @property string $path
 * @property string $mime
 * @property int $tamano
 * @property int|null $subido_por
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Property $property
 * @property-read User|null $uploader
 */
#[Fillable([
    'property_id', 'tipo', 'nota', 'nombre_original', 'path', 'mime', 'tamano', 'subido_por',
])]
class PropertyDocument extends Model
{
    /** @use HasFactory<PropertyDocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoPropiedad::class,
            'tamano' => 'integer',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /** Borra el archivo del disco y después la fila. */
    public function borrarConArchivo(): void
    {
        Storage::disk('local')->delete($this->path);
        $this->delete();
    }
}
