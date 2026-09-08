<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\User;

class ExpenseDocumentPolicy
{
    /** Subir un documento a un gasto de su propiedad. */
    public function create(User $user, Expense $expense): bool
    {
        return $user->puedeGestionar($expense->property);
    }

    /** Descargar / ver el archivo. */
    public function view(User $user, ExpenseDocument $document): bool
    {
        return $user->puedeGestionar($document->expense->property);
    }

    public function delete(User $user, ExpenseDocument $document): bool
    {
        return $user->puedeGestionar($document->expense->property);
    }
}
