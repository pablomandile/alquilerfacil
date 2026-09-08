<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Adjuntos de un formulario multipart con un input `archivos[]`. Mismas reglas
 * que los documentos del contrato: PDF, imágenes o Word, hasta 10 MB, validados
 * por extensión (el sniffeo de MIME da falsos negativos con los .docx).
 */
trait RecibeArchivosAdjuntos
{
    /** @return array<string, list<string>> */
    protected function reglasDeArchivos(): array
    {
        return [
            'archivos' => ['nullable', 'array', 'max:10'],
            'archivos.*' => ['file', 'max:10240', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx'],
        ];
    }

    /**
     * Los archivos ya validados (`archivos.*` => file). `file()` sólo devuelve
     * instancias de UploadedFile, así que acá no hace falta filtrar.
     *
     * @return list<UploadedFile>
     */
    protected function archivos(Request $request): array
    {
        $archivos = $request->file('archivos');

        return is_array($archivos) ? array_values($archivos) : [];
    }
}
