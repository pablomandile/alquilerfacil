<?php

namespace App\Listeners;

use App\Models\Owner;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Cuando alguien entra (con contraseña o con Google), si hay una ficha de
 * propietario cargada con su email y todavía sin cuenta, se la vincula.
 *
 * Así el flujo es: el admin carga al copropietario con su email → esa persona
 * entra con Google → ya ve su propiedad, sin ningún paso de invitación.
 */
class VincularOwnerAlIngresar
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->owner()->exists()) {
            return;
        }

        Owner::query()
            ->whereNull('user_id')
            ->where('email', $user->email)
            ->first()
            ?->forceFill(['user_id' => $user->id])
            ->save();
    }
}
