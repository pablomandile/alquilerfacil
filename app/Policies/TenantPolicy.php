<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Un inquilino no cuelga de una propiedad, así que no se puede acotar por dueño.
 * Cualquiera que gestione al menos una propiedad puede cargarlo y editarlo
 * (lo necesita para armar un contrato). Borrar queda sólo para el admin.
 */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->gestionaAlgunaPropiedad();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->gestionaAlgunaPropiedad();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return false;
    }
}
