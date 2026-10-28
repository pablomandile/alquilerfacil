<?php

namespace App\Services\Enlaces;

use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Acortador de zep.com.ar (API privada, token del admin en ZEP_TOKEN).
 *
 * Se llama mientras se arma una página, así que nunca corta: si no hay token o
 * Zep no responde, devuelve null y el que llama sigue con el enlace largo.
 */
class Zep
{
    public function acortar(string $url, ?DateTimeInterface $vence = null): ?string
    {
        $token = config('services.zep.token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $corto = Http::baseUrl((string) config('services.zep.url'))
                ->withToken($token)
                ->acceptJson()
                ->timeout(5)
                ->post('/links', array_filter([
                    'url' => $url,
                    'expires_at' => $vence?->format(DATE_ATOM),
                ]))
                ->throw()
                ->json('data.short_url');
        } catch (Throwable $e) {
            Log::warning('Zep: no se pudo acortar el enlace', ['error' => $e->getMessage()]);

            return null;
        }

        return is_string($corto) && $corto !== '' ? $corto : null;
    }
}
