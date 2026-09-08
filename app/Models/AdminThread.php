<?php

namespace App\Models;

use App\Enums\CategoriaTemaAdmin;
use App\Enums\EstadoTemaAdmin;
use Carbon\CarbonInterface;
use Database\Factories\AdminThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Un tema tratado con la administración del inmueble: un reclamo, un problema
 * informado, una consulta. El seguimiento son las entradas ({@see AdminThreadEntry}),
 * cada una fechada y con sus adjuntos.
 *
 * @property int $id
 * @property int $property_id
 * @property string $titulo
 * @property CategoriaTemaAdmin $categoria
 * @property EstadoTemaAdmin $estado
 * @property CarbonInterface|null $resuelto_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Property $property
 */
#[Fillable(['property_id', 'titulo', 'categoria', 'estado', 'resuelto_at'])]
class AdminThread extends Model
{
    /** @use HasFactory<AdminThreadFactory> */
    use HasFactory;

    /**
     * Al borrar el tema se van también los archivos de sus entradas. Las filas
     * de `admin_thread_entries` y `admin_thread_attachments` caen solas por las
     * foreign keys.
     */
    protected static function booted(): void
    {
        static::deleting(function (AdminThread $thread): void {
            Storage::disk('local')->deleteDirectory(
                "propiedades/{$thread->property_id}/administracion/{$thread->id}"
            );
        });
    }

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaTemaAdmin::class,
            'estado' => EstadoTemaAdmin::class,
            'resuelto_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return HasMany<AdminThreadEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(AdminThreadEntry::class)
            ->orderBy('fecha')
            ->orderBy('id');
    }
}
