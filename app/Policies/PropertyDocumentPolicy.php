<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\User;

class PropertyDocumentPolicy
{
    /** Subir un documento a una de sus propiedades. */
    public function create(User $user, Property $property): bool
    {
        return $user->puedeGestionar($property);
    }

    /** Descargar el archivo. */
    public function view(User $user, PropertyDocument $document): bool
    {
        return $user->puedeGestionar($document->property);
    }

    public function delete(User $user, PropertyDocument $document): bool
    {
        return $user->puedeGestionar($document->property);
    }
}
