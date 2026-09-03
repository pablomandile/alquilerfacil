<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Color de fondo inmediato (antes de que cargue app.css) para que no
             haya un flash blanco antes del degradado azul. Es el tono medio del
             degradado definido en resources/css/app.css. --}}
        <style>
            html {
                background-color: #bfd6f3;
            }

            html.dark {
                background-color: #0d192b;
            }
        </style>

        {{-- El ?v= fuerza a los navegadores a re-descargar el favicon, que
             cachean de forma muy agresiva (subir el número si se cambia). --}}
        <link rel="icon" href="/favicon.ico?v=2" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png?v=2">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
