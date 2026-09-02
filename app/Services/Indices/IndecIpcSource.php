<?php

namespace App\Services\Indices;

use App\Enums\Indice;
use App\Exceptions\FuenteDeIndiceException;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * IPC Nacional del INDEC, vía la API de Series de Tiempo de datos.gob.ar.
 *
 * Responde `{"data": [["2026-07-01", 12076.3937], ...]}`: el número índice con
 * base diciembre 2016, un valor por mes.
 *
 * Se guarda el número índice y no la variación porcentual porque dividir dos
 * índices da el coeficiente exacto de todo el período, mientras que encadenar
 * porcentajes mensuales acumula error de redondeo.
 */
class IndecIpcSource implements FuenteDeIndice
{
    use ConsultaHttp;

    public function indice(): Indice
    {
        return Indice::Ipc;
    }

    public function traer(?CarbonInterface $desde = null): Collection
    {
        $respuesta = $this->pedir(config('indices.ipc.url'), [
            'ids' => config('indices.ipc.serie'),
            'format' => 'json',
            'sort' => 'asc',
            'limit' => 1000,
            // La API espera el primer día del mes.
            'start_date' => $desde?->copy()->startOfMonth()->toDateString(),
        ]);

        $data = $respuesta['data'] ?? null;

        if (! is_array($data)) {
            throw FuenteDeIndiceException::respuestaInesperada($this->indice());
        }

        return collect($data)
            // Los pares vienen como [fecha, valor]; el valor puede ser null en
            // meses todavía sin publicar.
            ->filter(fn ($par) => is_array($par) && isset($par[0], $par[1]))
            ->map(fn (array $par) => [
                'fecha' => Date::parse($par[0])->startOfMonth(),
                'valor' => $this->comoDecimal($par[1]),
            ])
            ->values();
    }
}
