<?php

namespace App\Support;

/**
 * Operaciones decimales sobre strings.
 *
 * Las funciones de bcmath truncan en la escala pedida en vez de redondear, que
 * para plata no es lo que uno quiere: $1.234,567 tiene que quedar en $1.234,57 y
 * no en $1.234,56. Estos helpers redondean de verdad.
 */
class Decimal
{
    /** Redondea media unidad hacia arriba, como se espera con importes. */
    public static function redondear(string $numero, int $decimales = 2): string
    {
        $mitad = bcdiv('1', bcpow('10', (string) ($decimales + 1), 0), $decimales + 1);

        $ajustado = bccomp($numero, '0', $decimales + 2) < 0
            ? bcsub($numero, $mitad, $decimales + 1)
            : bcadd($numero, $mitad, $decimales + 1);

        return bcadd($ajustado, '0', $decimales);
    }

    /**
     * Redondea al múltiplo más cercano. Se usa para dejar el alquiler en un
     * número "lindo" ($478.000 en vez de $477.913,44), que es lo que se pacta.
     */
    public static function aMultiploDe(string $numero, int $multiplo): string
    {
        if ($multiplo <= 1) {
            return self::redondear($numero);
        }

        $veces = self::redondear(bcdiv($numero, (string) $multiplo, 8), 0);

        return bcmul($veces, (string) $multiplo, 2);
    }

    /** Variación porcentual a partir de un coeficiente: 1,062 -> 6,20. */
    public static function coeficienteAPorcentaje(string $coeficiente): string
    {
        return self::redondear(bcmul(bcsub($coeficiente, '1', 10), '100', 10), 4);
    }
}
