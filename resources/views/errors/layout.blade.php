<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>@yield('title') - {{ config('app.name', 'AUFlow') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-background text-foreground antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-3xl items-center justify-center px-6 py-16">
            <section class="w-full rounded-xl border border-border/60 bg-card p-8 shadow-sm">
                @yield('content')
            </section>
        </main>
    </body>
</html>
