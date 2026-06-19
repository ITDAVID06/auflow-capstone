<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
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
            html { background-color: oklch(0.982 0.005 85); }
            html.dark { background-color: oklch(0.115 0.015 258); }
        </style>

        @php
            // Brand
            $appName = config('app.name', 'AUFlow Portal');
            // Vite-resolved path for the PNG so it also works in production builds
            $appIcon = Vite::asset('resources/js/assets/auf_logo.png');
        @endphp

        <title inertia>{{ $appName }}</title>
        <meta name="application-name" content="{{ $appName }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0d1120" media="(prefers-color-scheme: dark)">
        <meta name="theme-color" content="#faf9f7" media="(prefers-color-scheme: light)">

        {{-- Favicon / App Icons (remove Laravel defaults) --}}
        <link rel="icon" href="{{ $appIcon }}" type="image/png">
        <link rel="apple-touch-icon" href="{{ $appIcon }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|syne:400,500,600,700,800&display=swap" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
