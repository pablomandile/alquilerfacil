<?php

namespace App\Policies;

use App\Models\User;

class RentChargePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Emitir los cargos del mes. El propietario puede: el generador sólo toca
     * los contratos de sus propiedades (ver GeneradorDeCargos).
     */
    public function generar(User $user): bool
    {
        return $user->gestionaAlgunaPropiedad();
    }
}
