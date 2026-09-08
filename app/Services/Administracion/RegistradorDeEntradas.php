<?php

namespace App\Services\Administracion;

use App\Models\AdminThread;
use App\Models\AdminThreadEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Crea una entrada de seguimiento y guarda sus adjuntos en el disco privado.
 * Lo usan tanto el alta de un tema (su primera entrada) como el agregado de una
 * entrada nueva, para que la lógica de archivos viva en un solo lugar.
 */
class RegistradorDeEntradas
{
    /**
     * @param  list<UploadedFile>  $archivos
     */
    public function registrar(
        AdminThread $tema,
        string $fecha,
        string $detalle,
        array $archivos,
        ?User $usuario,
    ): AdminThreadEntry {
        $entrada = $tema->entries()->create([
            'fecha' => $fecha,
            'detalle' => $detalle,
            'registrado_por' => $usuario?->id,
        ]);

        foreach ($archivos as $archivo) {
            $path = $archivo->storeAs(
                "propiedades/{$tema->property_id}/administracion/{$tema->id}",
                Str::ulid().'.'.strtolower($archivo->getClientOriginalExtension()),
                'local',
            );

            if ($path === false) {
                continue;
            }

            $entrada->attachments()->create([
                'nombre_original' => $archivo->getClientOriginalName(),
                'path' => $path,
                'mime' => $archivo->getMimeType() ?? $archivo->getClientMimeType(),
                'tamano' => $archivo->getSize() ?: 0,
                'subido_por' => $usuario?->id,
            ]);
        }

        return $entrada;
    }
}
