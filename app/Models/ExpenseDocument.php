<?php

namespace App\Models;

use App\Enums\TipoDocumentoGasto;
use App\Services\Enlaces\Zep;
use Carbon\CarbonInterface;
use Database\Factories\ExpenseDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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
 * @property string|null $enlace_corto
 * @property CarbonInterface|null $enlace_corto_vence_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Expense $expense
 * @property-read User|null $uploader
 */
#[Fillable([
    'expense_id', 'tipo', 'nombre_original', 'path', 'mime', 'tamano', 'subido_por',
    'enlace_corto', 'enlace_corto_vence_at',
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
            'enlace_corto_vence_at' => 'datetime',
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

    /** Lo que dura la firma del enlace que se le pasa al inquilino. */
    private const DIAS_DE_FIRMA = 60;

    /** Lo mínimo que le tiene que quedar a un enlace guardado para reusarlo. */
    private const DIAS_MINIMOS = 30;

    /**
     * Enlace para pasárselo al inquilino sin que inicie sesión: firmado, con
     * vencimiento y acortado con Zep. El corto se guarda y se reusa mientras le
     * queden al menos 30 días, así cualquier aviso que se mande dura un mes y no
     * se crea un link nuevo en Zep cada vez que se abre la página. Si Zep no
     * responde, va el enlace firmado largo.
     */
    public function enlaceCompartido(): string
    {
        if ($this->enlace_corto !== null
            && $this->enlace_corto_vence_at?->greaterThan(now()->addDays(self::DIAS_MINIMOS))) {
            return $this->enlace_corto;
        }

        $vence = now()->addDays(self::DIAS_DE_FIRMA);
        $largo = URL::temporarySignedRoute(
            'gastos.documentos.compartido',
            $vence,
            ['document' => $this->id],
        );

        $corto = app(Zep::class)->acortar($largo, $vence);

        if ($corto === null) {
            return $largo;
        }

        $this->forceFill(['enlace_corto' => $corto, 'enlace_corto_vence_at' => $vence])->saveQuietly();

        return $corto;
    }

    /** Borra el archivo del disco y después la fila. */
    public function borrarConArchivo(): void
    {
        Storage::disk('local')->delete($this->path);
        $this->delete();
    }
}
