<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>{{ $dealer->name }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        html { font-size: 18px; }
        #miniapp .text-\[10px\] { font-size: 0.625rem; }
        #miniapp .text-\[11px\] { font-size: 0.6875rem; }
        #miniapp .text-\[12px\] { font-size: 0.75rem; }
    </style>
    @vite('resources/js/miniapp/main.ts')
</head>
<body>
    <div id="miniapp"
        data-dealer-id="{{ $dealer->id }}"
        data-dealer-name="{{ $dealer->name }}"
        data-dealer-visibility="{{ $dealer->visibility?->value ?? 'private' }}"
        data-locale="{{ app()->getLocale() }}"
    ></div>
</body>
</html>
