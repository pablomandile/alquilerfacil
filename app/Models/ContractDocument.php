<?php

namespace App\Models;

use App\Enums\TipoDocumentoContrato;
use Carbon\CarbonInterface;
use Database\Factories\ContractDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Un archivo adjunto a un contrato: el contrato firmado, la garantía, el pagaré,
 * el seguro de caución, etc. Vive en el disco privado `local` y se descarga por
 * el ContractDocumentController, nunca por URL directa.
 *
 * @property int $id
 * @property int $contract_id
 * @property TipoDocumentoContrato $tipo
 * @property string|null $nota
 * @property string $nombre_original
 * @property string $path
 * @property string $mime
 * @property int $tamano
 * @property int|null $subido_por
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Contract $contract
 * @property-read User|null $uploader
 */
#[Fillable([
    'contract_id', 'tipo', 'nota', 'nombre_original', 'path', 'mime', 'tamano', 'subido_por',
])]
class ContractDocument extends Model
{
    /** @use HasFactory<ContractDocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoContrato::class,
            'tamano' => 'integer',
        ];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
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
