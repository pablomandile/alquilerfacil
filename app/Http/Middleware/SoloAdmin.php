<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar sólo al administrador.
 *
 * Los propietarios entran a la app en modo lectura: ven sus propiedades, sus
 * contratos y su parte del alquiler, pero no cargan ni modifican nada. Toda ruta
 * que escribe pasa por acá, así no depende de recordar autorizar en cada
 * controlador.
 */
class SoloAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->esAdmin(), 403, 'Sólo el administrador puede hacer esto.');

        return $next($request);
    }
}
