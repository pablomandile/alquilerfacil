<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\Property;
use App\Models\User;

/**
 * El admin pasa por Gate::before. Acá sólo se decide para el propietario, que
 * gestiona los gastos de las propiedades donde es dueño.
 */
class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->puedeGestionar($expense->property);
    }

    public function create(User $user, ?Property $property = null): bool
    {
        return $property !== null
            ? $user->puedeGestionar($property)
            : $user->gestionaAlgunaPropiedad();
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->puedeGestionar($expense->property);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->puedeGestionar($expense->property);
    }
}
