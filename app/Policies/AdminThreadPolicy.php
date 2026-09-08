<?php

namespace App\Policies;

use App\Models\AdminThread;
use App\Models\Property;
use App\Models\User;

class AdminThreadPolicy
{
    /** Abrir un tema de seguimiento en una de sus propiedades. */
    public function create(User $user, Property $property): bool
    {
        return $user->puedeGestionar($property);
    }

    public function update(User $user, AdminThread $thread): bool
    {
        return $user->puedeGestionar($thread->property);
    }

    public function delete(User $user, AdminThread $thread): bool
    {
        return $user->puedeGestionar($thread->property);
    }
}
