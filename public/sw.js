/*
 * Service worker de Alquiler Fácil.
 *
 * Es una app de gestión: necesita el servidor sí o sí. El SW existe para (1)
 * cumplir el requisito de instalabilidad de la PWA y (2) servir más rápido los
 * assets con hash. NO intenta funcionar offline salvo mostrar un cartel.
 *
 * Reglas:
 *  - cache-first SÓLO para /build/ (llevan hash de contenido: la URL nunca
 *    apunta a otra cosa).
 *  - Todo lo demás: network-first. Las URLs fijas (íconos, manifest) quedarían
 *    congeladas con cache-first.
 *  - Nunca se guarda una respuesta de Inertia (lleva x-inertia y su propio
 *    no-store): si el navegador la sirviera cruda a una navegación se ve el
 *    JSON en pantalla.
 */

const CACHE = 'af-v1'; // subir al cambiar íconos/manifest: activate borra las otras
const PRECACHE = ['/offline.html', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((c) => c.addAll(PRECACHE))
            .catch(() => {}),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((k) => k !== CACHE)
                        .map((k) => caches.delete(k)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

const esAssetConHash = (url) => url.pathname.startsWith('/build/');

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Navegación de documento (barra de direcciones, F5, atrás/adelante). Los
    // <Link> de Inertia NO pasan por acá: son XHR, con mode 'cors'/'same-origin'.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((res) => {
                    // Si vuelve el JSON de Inertia para una navegación (el CDN a
                    // veces cruza las cachés), re-pedir el HTML de arranque.
                    if (res.headers.get('x-inertia')) {
                        return fetch(url.href, {
                            cache: 'reload',
                            credentials: 'include',
                            headers: { Accept: 'text/html' },
                        }).then((html) =>
                            html.redirected
                                ? Response.redirect(html.url, 302)
                                : html,
                        );
                    }
                    return res;
                })
                .catch(() => caches.match('/offline.html')),
        );
        return;
    }

    // Assets con hash: cache-first.
    if (esAssetConHash(url)) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ||
                    fetch(request).then((res) => {
                        const copy = res.clone();
                        caches.open(CACHE).then((c) => c.put(request, copy));
                        return res;
                    }),
            ),
        );
        return;
    }

    // Resto: network-first, caché como respaldo. Nunca guardar Inertia.
    event.respondWith(
        fetch(request)
            .then((res) => {
                if (
                    res.ok &&
                    res.type === 'basic' &&
                    !res.headers.get('x-inertia')
                ) {
                    const copy = res.clone();
                    caches.open(CACHE).then((c) => c.put(request, copy));
                }
                return res;
            })
            .catch(() => caches.match(request)),
    );
});
