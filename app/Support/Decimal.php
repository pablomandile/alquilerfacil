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
    /**
     * Normaliza un valor cualquiera (típico: un campo de formulario ya validado
     * como `numeric`) a un decimal string apto para bcmath. Lo no numérico da '0'.
     *
     * @return numeric-string
     */
    public static function desde(mixed $valor, int $decimales = 2): string
    {
        return is_numeric($valor) ? bcadd((string) $valor, '0', $decimales) : '0';
    }

    /**
     * Suma una lista de importes con bcmath, a la escala pedida.
     *
     * @param  iterable<numeric-string>  $numeros
     * @return numeric-string
     */
    public static function sumar(iterable $numeros, int $decimales = 2): string
    {
        $total = '0';

        foreach ($numeros as $numero) {
            $total = bcadd($total, $numero, $decimales);
        }

        return $total;
    }

    /**
     * Redondea media unidad hacia arriba, como se espera con importes.
     *
     * @param  numeric-string  $numero
     * @return numeric-string
     */
    public static function redondear(string $numero, int $decimales = 2): string
    {
        // Media unidad de la última posición: 0,005 para dos decimales, 0,5 para
        // ninguno. Sumarla (o restarla, si el número es negativo) antes de truncar
        // en la escala pedida deja el resultado redondeado media unidad hacia afuera.
        $mitad = bcdiv('5', bcpow('10', (string) ($decimales + 1), 0), $decimales + 1);

        $ajustado = bccomp($numero, '0', $decimales + 2) < 0
            ? bcsub($numero, $mitad, $decimales + 1)
            : bcadd($numero, $mitad, $decimales + 1);

        return bcadd($ajustado, '0', $decimales);
    }

    /**
     * Deja un importe en pesos enteros (sin centavos), con la escala habitual de
     * los montos. El alquiler se lleva siempre así.
     *
     * @param  numeric-string  $numero
     * @return numeric-string
     */
    public static function aPesosEnteros(string $numero): string
    {
        return bcadd(self::redondear($numero, 0), '0', 2);
    }

    /**
     * Redondea al múltiplo más cercano. Se usa para dejar el alquiler en un
     * número "lindo" ($478.000 en vez de $477.913,44), que es lo que se pacta.
     *
     * Sin múltiplo (o con 0/1) el alquiler igual queda en pesos enteros: los
     * centavos no se usan nunca en el importe.
     *
     * @param  numeric-string  $numero
     * @return numeric-string
     */
    public static function aMultiploDe(string $numero, int $multiplo): string
    {
        if ($multiplo <= 1) {
            return self::aPesosEnteros($numero);
        }

        $veces = self::redondear(bcdiv($numero, (string) $multiplo, 8), 0);

        return bcmul($veces, (string) $multiplo, 2);
    }

    /**
     * Variación porcentual a partir de un coeficiente: 1,062 -> 6,20.
     *
     * @param  numeric-string  $coeficiente
     * @return numeric-string
     */
    public static function coeficienteAPorcentaje(string $coeficiente): string
    {
        return self::redondear(bcmul(bcsub($coeficiente, '1', 10), '100', 10), 4);
    }
}
