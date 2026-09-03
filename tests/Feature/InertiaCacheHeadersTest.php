<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Tests\TestCase;

/**
 * El CDN de Hostinger borra el `Vary: X-Inertia`, así que una caché no distingue
 * el HTML de arranque del JSON de la página. Sin `no-store` en la respuesta XHR,
 * al restaurar una pestaña descartada Chrome muestra el JSON crudo.
 */
class InertiaCacheHeadersTest extends TestCase
{
    /** La versión del asset, o Inertia contesta 409 en vez de la página. */
    private function versionDeInertia(): string
    {
        return (string) app(HandleInertiaRequests::class)->version(request());
    }

    public function test_prohibe_guardar_la_respuesta_xhr_de_inertia()
    {
        $response = $this->get('/login', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $this->versionDeInertia(),
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_deja_cacheable_el_documento_html_para_no_perder_el_bfcache()
    {
        $response = $this->get('/login');

        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
