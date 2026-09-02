<?php

namespace App\Services\Indices;

use App\Exceptions\FuenteDeIndiceException;
use Composer\CaBundle\CaBundle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Lo que comparten las fuentes de índices: cómo se pide (timeout, reintentos,
 * manejo de errores) y cómo se normalizan los números.
 */
trait ConsultaHttp
{
    protected function cliente(): PendingRequest
    {
        return Http::timeout(config('indices.http.timeout'))
            ->retry(
                config('indices.http.reintentos'),
                config('indices.http.espera_ms'),
                throw: false
            )
            ->acceptJson()
            // Verificación TLS siempre activa, contra un bundle que viaja con el
            // proyecto: así no depende de qué tan viejo esté el del servidor.
            ->withOptions(['verify' => $this->caBundle()]);
    }

    protected function caBundle(): string
    {
        return config('indices.http.ca_bundle')
            ?: CaBundle::getBundledCaBundlePath();
    }

    /**
     * Hace la consulta y devuelve el JSON decodificado, convirtiendo cualquier
     * falla en una excepción con un mensaje que se entienda en pantalla.
     *
     * @throws FuenteDeIndiceException
     */
    protected function pedir(string $url, array $params): array
    {
        try {
            $respuesta = $this->cliente()->get($url, array_filter(
                $params,
                fn ($v) => $v !== null && $v !== ''
            ));
        } catch (ConnectionException $e) {
            throw FuenteDeIndiceException::noRespondio($this->indice(), $e->getMessage(), $e);
        }

        if ($respuesta->failed()) {
            throw FuenteDeIndiceException::noRespondio(
                $this->indice(),
                "la API respondió HTTP {$respuesta->status()}"
            );
        }

        return $respuesta->json() ?? [];
    }

    /**
     * Pasa el número a string sin notación científica: los valores van a una
     * columna decimal y se operan con bcmath, que trabaja sobre strings.
     */
    protected function comoDecimal(float|int|string $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 8, '.', ''), '0'), '.') ?: '0';
    }
}
