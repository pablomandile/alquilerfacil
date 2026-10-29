<?php

namespace App\Services\Administracion;

use App\Models\AdminThread;
use App\Models\AdminThreadEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Crea y edita las entradas de seguimiento y guarda sus adjuntos en el disco
 * privado. Lo usan el alta de un tema (su primera entrada), el agregado de una
 * entrada nueva y la edición de una existente, para que la lógica de archivos
 * viva en un solo lugar.
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

        $this->adjuntar($entrada, $archivos, $usuario);

        return $entrada;
    }

    /**
     * Corrige la fecha y el texto de una entrada y le suma adjuntos. Los que ya
     * tenía quedan: se borran uno por uno desde su propio botón.
     *
     * @param  list<UploadedFile>  $archivos
     */
    public function editar(
        AdminThreadEntry $entrada,
        string $fecha,
        string $detalle,
        array $archivos,
        ?User $usuario,
    ): AdminThreadEntry {
        $entrada->update(['fecha' => $fecha, 'detalle' => $detalle]);

        $this->adjuntar($entrada, $archivos, $usuario);

        return $entrada;
    }

    /**
     * @param  list<UploadedFile>  $archivos
     */
    private function adjuntar(AdminThreadEntry $entrada, array $archivos, ?User $usuario): void
    {
        $tema = $entrada->thread;

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
    }
}
