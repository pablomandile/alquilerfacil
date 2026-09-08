<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AdminThreadEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Una entrada del seguimiento de un tema con la administración: qué pasó y
 * cuándo. Puede llevar adjuntos ({@see AdminThreadAttachment}).
 *
 * @property int $id
 * @property int $admin_thread_id
 * @property CarbonInterface $fecha
 * @property string $detalle
 * @property int|null $registrado_por
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read AdminThread $thread
 * @property-read User|null $registrador
 */
#[Fillable(['admin_thread_id', 'fecha', 'detalle', 'registrado_por'])]
class AdminThreadEntry extends Model
{
    /** @use HasFactory<AdminThreadEntryFactory> */
    use HasFactory;

    /**
     * Borrar una entrada suelta se lleva sus archivos del disco. (Cuando cae el
     * tema entero lo limpia el hook de AdminThread, que borra el directorio.)
     */
    protected static function booted(): void
    {
        static::deleting(function (AdminThreadEntry $entry): void {
            foreach ($entry->attachments as $adjunto) {
                Storage::disk('local')->delete($adjunto->path);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /** @return BelongsTo<AdminThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(AdminThread::class, 'admin_thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** @return HasMany<AdminThreadAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(AdminThreadAttachment::class)->oldest();
    }
}
