<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Una URL de Inertia devuelve el HTML de arranque a una navegación y el JSON
     * de la página a un XHR; lo único que las distingue para una caché es
     * `Vary: X-Inertia`. El CDN de Hostinger reescribe ese Vary (y lo borra al
     * comprimir con brotli), así que el navegador guarda el JSON bajo la URL
     * pelada y, al restaurar una pestaña descartada, lo muestra crudo en vez de
     * la página.
     *
     * `no-store` (no `no-cache`, que permite guardar y solo obliga a revalidar)
     * y **solo sobre la respuesta XHR**: `no-store` en el documento HTML mata el
     * back/forward cache de Chrome.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        $response->headers->set('Vary', Header::INERTIA.', Accept-Encoding');

        if ($request->header(Header::INERTIA)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                // Para esconder botones que igual darían 403. La restricción de
                // verdad la hacen el middleware `admin` y las policies.
                'esAdmin' => (bool) $user?->esAdmin(),
                'esPropietario' => (bool) $user?->esPropietario(),
                // ¿Gestiona al menos una propiedad? (gatea los botones "Nuevo…").
                'puedeGestionar' => (bool) $user?->gestionaAlgunaPropiedad(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
