<?php

namespace App\Models;

use App\Enums\TipoDocumentoGasto;
use Carbon\CarbonInterface;
use Database\Factories\ExpenseDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Un archivo adjunto a un gasto: la factura o la expensa del período, o el
 * comprobante del pago. Vive en el disco privado `local` y se descarga por el
 * ExpenseDocumentController, nunca por URL directa.
 *
 * @property int $id
 * @property int $expense_id
 * @property TipoDocumentoGasto $tipo
 * @property string $nombre_original
 * @property string $path
 * @property string $mime
 * @property int $tamano
 * @property int|null $subido_por
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Expense $expense
 * @property-read User|null $uploader
 */
#[Fillable([
    'expense_id', 'tipo', 'nombre_original', 'path', 'mime', 'tamano', 'subido_por',
])]
class ExpenseDocument extends Model
{
    /** @use HasFactory<ExpenseDocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoGasto::class,
            'tamano' => 'integer',
        ];
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
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
