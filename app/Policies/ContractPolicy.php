<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\Property;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->puedeGestionar($contract->property);
    }

    public function create(User $user, ?Property $property = null): bool
    {
        return $property !== null
            ? $user->puedeGestionar($property)
            : $user->gestionaAlgunaPropiedad();
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->puedeGestionar($contract->property);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->puedeGestionar($contract->property);
    }
}
