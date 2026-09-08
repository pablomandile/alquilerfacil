<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega un archivo del disco privado: inline por defecto (para verlo en el
 * visor), o forzando la descarga con `?descarga=1`.
 */
trait EntregaArchivoPrivado
{
    protected function entregarArchivo(Request $request, string $path, string $nombre): StreamedResponse
    {
        $disk = Storage::disk('local');

        // `nosniff` + la validación por extensión (sin svg ni html) evitan que
        // un adjunto se interprete como HTML servido desde el mismo dominio.
        return $request->boolean('descarga')
            ? $disk->download($path, $nombre)
            : $disk->response($path, $nombre, ['X-Content-Type-Options' => 'nosniff']);
    }
}
