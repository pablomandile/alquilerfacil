<?php

namespace App\Contracts;

use App\Models\OwnerShare;
use App\Models\Property;
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

    /** El monto total a repartir, como string decimal apto para bcmath. */
    public function montoARepartir(): string;

    /** @return MorphMany<OwnerShare, static> */
    public function shares(): MorphMany;
}
