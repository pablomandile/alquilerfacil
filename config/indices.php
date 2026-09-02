<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuentes de índices oficiales
    |--------------------------------------------------------------------------
    |
    | Ambas APIs son públicas y no requieren autenticación.
    |
    */

    'ipc' => [
        // API de Series de Tiempo de datos.gob.ar. Devuelve el número índice
        // mensual del IPC Nacional (base diciembre 2016), publicado por INDEC.
        'url' => env('IPC_API_URL', 'https://apis.datos.gob.ar/series/api/series/'),
        'serie' => env('IPC_SERIE_ID', '148.3_INIVELNAL_DICI_M_26'),

        // El INDEC publica el IPC de un mes a mediados del mes siguiente.
        // Se usa para avisar cuándo se espera el índice que falta.
        'dia_de_publicacion' => 15,
    ],

    'icl' => [
        // Índice para Contratos de Locación del BCRA (base 30/6/2020 = 1).
        // Es diario.
        'url' => env('ICL_API_URL', 'https://api.bcra.gob.ar/estadisticas/v4.0/Monetarias/40'),

        // Fecha base del índice: no tiene sentido pedir datos anteriores.
        'desde_inicial' => '2020-06-30',
    ],

    'http' => [
        'timeout' => 30,
        'reintentos' => 3,
        'espera_ms' => 500,

        /*
         * El BCRA sirve su certificado sin la raíz de la cadena, así que la
         * validación depende del bundle de CA del servidor. Cuando ese bundle está
         * viejo —el de Laragon venía de 2022, y en hosting compartido no se
         * controla php.ini— cURL corta con el error 60 y la sincronización del ICL
         * falla entera.
         *
         * Por eso se usa el bundle que trae composer/ca-bundle en vez del del
         * sistema: viaja con el proyecto y es el mismo en local y en producción.
         * Se puede apuntar a otro archivo con INDICES_CA_BUNDLE.
         */
        'ca_bundle' => env('INDICES_CA_BUNDLE'),
    ],

];
