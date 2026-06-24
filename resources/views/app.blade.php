<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
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

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Self-hosted Instrument Sans — bunny.net third-party dependency olib tashlandi --}}
        <link
            rel="preload"
            href="/fonts/instrument-sans-latin-400-normal.woff2"
            as="font"
            type="font/woff2"
            crossorigin="anonymous"
        >
        <style>
            @font-face {
                font-family: 'Instrument Sans';
                font-style: normal;
                font-weight: 400;
                font-display: swap;
                src: url('/fonts/instrument-sans-latin-400-normal.woff2') format('woff2');
            }
            @font-face {
                font-family: 'Instrument Sans';
                font-style: normal;
                font-weight: 500;
                font-display: swap;
                src: url('/fonts/instrument-sans-latin-500-normal.woff2') format('woff2');
            }
            @font-face {
                font-family: 'Instrument Sans';
                font-style: normal;
                font-weight: 600;
                font-display: swap;
                src: url('/fonts/instrument-sans-latin-600-normal.woff2') format('woff2');
            }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
