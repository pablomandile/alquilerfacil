<?php

namespace App\Policies;

use App\Models\AdminThreadAttachment;
use App\Models\User;

class AdminThreadAttachmentPolicy
{
    /** Descargar el archivo. */
    public function view(User $user, AdminThreadAttachment $attachment): bool
    {
        return $user->puedeGestionar($attachment->entry->thread->property);
    }

    public function delete(User $user, AdminThreadAttachment $attachment): bool
    {
        return $user->puedeGestionar($attachment->entry->thread->property);
    }
}
