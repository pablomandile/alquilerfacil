<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AdminThreadAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Un archivo adjunto a una entrada del seguimiento con la administración: la
 * carta documento, la respuesta del consorcio, una foto del desperfecto, etc.
 * Vive en el disco privado `local` y se descarga por el controlador.
 *
 * @property int $id
 * @property int $admin_thread_entry_id
 * @property string $nombre_original
 * @property string $path
 * @property string $mime
 * @property int $tamano
 * @property int|null $subido_por
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read AdminThreadEntry $entry
 * @property-read User|null $uploader
 */
#[Fillable([
    'admin_thread_entry_id', 'nombre_original', 'path', 'mime', 'tamano', 'subido_por',
])]
class AdminThreadAttachment extends Model
{
    /** @use HasFactory<AdminThreadAttachmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tamano' => 'integer',
        ];
    }

    /** @return BelongsTo<AdminThreadEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(AdminThreadEntry::class, 'admin_thread_entry_id');
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
