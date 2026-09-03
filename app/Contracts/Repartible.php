<?php

namespace App\Contracts;

use App\Models\OwnerShare;
use App\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Algo cuyo monto se reparte entre los propietarios de una propiedad.
 *
 * Lo implementan RentCharge (el alquiler del mes) y Expense (los gastos a cargo
 * de los dueños), que son las dos cosas que el negocio reparte con la misma regla.
 */
interface Repartible
{
    /** La propiedad de cuyos dueños sale el reparto. */
    public function propiedadDelReparto(): Property;

    /**
     * El monto total a repartir, como string decimal apto para bcmath.
     *
     * @return numeric-string
     */
    public function montoARepartir(): string;

    /**
     * El reparto entre dueños, como relación polimórfica.
     *
     * El segundo parámetro de MorphMany (el modelo declarante) va como
     * `covariant`: cada implementación lo devuelve tipado a sí misma
     * (`$this->morphMany(...)`), y sin esto phpstan lo trata como invariante.
     *
     * @return MorphMany<OwnerShare, covariant Model>
     */
    public function shares(): MorphMany;
}
