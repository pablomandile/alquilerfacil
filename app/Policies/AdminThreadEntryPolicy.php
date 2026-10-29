<?php

namespace App\Policies;

use App\Models\AdminThread;
use App\Models\AdminThreadEntry;
use App\Models\User;

class AdminThreadEntryPolicy
{
    /** Agregar una entrada de seguimiento a un tema. */
    public function create(User $user, AdminThread $thread): bool
    {
        return $user->puedeGestionar($thread->property);
    }

    /** Editar una entrada y sumarle adjuntos. */
    public function update(User $user, AdminThreadEntry $entry): bool
    {
        return $user->puedeGestionar($entry->thread->property);
    }

    public function delete(User $user, AdminThreadEntry $entry): bool
    {
        return $user->puedeGestionar($entry->thread->property);
    }
}
