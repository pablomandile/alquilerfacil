<?php

namespace App\Policies;

use App\Models\RentAdjustment;
use App\Models\User;

class RentAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Aplicar o rechazar una propuesta de ajuste del contrato de su propiedad. */
    public function resolver(User $user, RentAdjustment $adjustment): bool
    {
        return $user->puedeGestionar($adjustment->contract->property);
    }
}
