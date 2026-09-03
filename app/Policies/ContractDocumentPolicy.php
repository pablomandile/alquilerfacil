<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\User;

class ContractDocumentPolicy
{
    /** Subir un documento a un contrato de su propiedad. */
    public function create(User $user, Contract $contract): bool
    {
        return $user->puedeGestionar($contract->property);
    }

    /** Descargar el archivo. */
    public function view(User $user, ContractDocument $document): bool
    {
        return $user->puedeGestionar($document->contract->property);
    }

    public function delete(User $user, ContractDocument $document): bool
    {
        return $user->puedeGestionar($document->contract->property);
    }
}
