<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\User;

class PaymentPolicy
{
    /** Registrar un pago contra un cargo del contrato de su propiedad. */
    public function create(User $user, RentCharge $charge): bool
    {
        return $user->puedeGestionar($charge->contract->property);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->puedeGestionar($payment->rentCharge->contract->property);
    }
}
