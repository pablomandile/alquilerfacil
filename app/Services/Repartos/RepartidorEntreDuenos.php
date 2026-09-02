<?php

namespace App\Services\Repartos;

use App\Contracts\Repartible;
use App\Exceptions\RepartoInvalidoException;
use App\Models\Owner;
use App\Models\OwnerShare;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reparte un monto entre los propietarios de una propiedad según su porcentaje.
 *
 * El reparto se persiste (no se recalcula al vuelo) porque los porcentajes de
 * propiedad cambian con el tiempo: si se recalculara, un cargo de hace un año se
 * re-repartiría con los porcentajes de hoy y la historia quedaría mal.
 */
class RepartidorEntreDuenos
{
    /**
     * Calcula y guarda el reparto, reemplazando el anterior si lo hubiera.
     *
     * @return Collection<int, OwnerShare>
     *
     * @throws RepartoInvalidoException
     */
    public function repartir(Repartible $item): Collection
    {
        $property = $item->propiedadDelReparto();
        $monto = $item->montoARepartir();

        $propietarios = $property->owners()->get();

        if ($propietarios->isEmpty()) {
            throw RepartoInvalidoException::sinPropietarios($property);
        }

        $suma = $propietarios->reduce(
            fn (string $acc, Owner $o) => bcadd($acc, (string) $o->pivot->porcentaje, 2),
            '0'
        );

        // El ajuste de residuo de abajo corrige centavos de redondeo, no errores de
        // carga. Si los porcentajes no suman 100 hay un problema de datos que tiene
        // que salir a la luz, no taparse sumándole la diferencia a alguien.
        if (bccomp($suma, '100', 2) !== 0) {
            throw RepartoInvalidoException::porcentajesNoSuman100($property, $suma);
        }

        $partes = $this->calcularPartes($monto, $propietarios);

        return DB::transaction(function () use ($item, $partes) {
            $item->shares()->delete();

            return collect($partes)->map(
                fn (array $parte) => $item->shares()->create([
                    'owner_id' => $parte['owner_id'],
                    'porcentaje' => $parte['porcentaje'],
                    'monto' => $parte['monto'],
                ])
            );
        });
    }

    /**
     * Reparte el monto truncando cada parte a centavos y asignando el residuo al
     * dueño de mayor porcentaje.
     *
     * Truncar y luego asignar el resto es lo que garantiza que las partes sumen
     * exactamente el total. Redondear cada parte por separado no lo garantiza: con
     * tres dueños al 33,33 / 33,33 / 33,34 % de $450.000, las partes redondeadas
     * dan $449.999,99 y se pierde un centavo.
     *
     * El desempate por menor owner_id hace que el reparto sea determinístico: dos
     * corridas sobre los mismos datos dan siempre el mismo resultado.
     *
     * @param  Collection<int, Owner>  $propietarios
     * @return list<array{owner_id: int, porcentaje: string, monto: string}>
     */
    private function calcularPartes(string $monto, Collection $propietarios): array
    {
        $partes = $propietarios
            // Mayor porcentaje primero y, a igual porcentaje, menor id: así el
            // residuo cae siempre en el mismo dueño ante los mismos datos.
            ->sort(fn (Owner $a, Owner $b) => bccomp(
                (string) $b->pivot->porcentaje,
                (string) $a->pivot->porcentaje,
                2
            ) ?: $a->id <=> $b->id)
            ->values()
            ->map(function (Owner $owner) use ($monto) {
                $porcentaje = (string) $owner->pivot->porcentaje;

                return [
                    'owner_id' => $owner->id,
                    'porcentaje' => $porcentaje,
                    // bcdiv trunca en la escala pedida, no redondea: es justo lo que
                    // queremos para que nunca se reparta de más.
                    'monto' => bcdiv(bcmul($monto, $porcentaje, 10), '100', 2),
                ];
            })
            ->all();

        $repartido = array_reduce(
            $partes,
            fn (string $acc, array $parte) => bcadd($acc, $parte['monto'], 2),
            '0'
        );

        $residuo = bcsub($monto, $repartido, 2);

        if (bccomp($residuo, '0', 2) !== 0) {
            $partes[0]['monto'] = bcadd($partes[0]['monto'], $residuo, 2);
        }

        return $partes;
    }
}
