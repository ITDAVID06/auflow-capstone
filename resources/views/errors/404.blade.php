@extends('errors.layout')

@section('title', 'Page Not Found')

@section('content')
    <div class="space-y-6 text-center">
        <p class="text-sm font-medium uppercase tracking-[0.12em] text-muted-foreground">404</p>
        <h1 class="text-3xl font-semibold">Page Not Found</h1>
        <p class="mx-auto max-w-xl text-sm text-muted-foreground">
            The page you are looking for does not exist or may have been moved.
        </p>
        <a
            href="{{ url('/') }}"
            class="inline-flex items-center justify-center rounded-md border border-border px-4 py-2 text-sm font-medium transition hover:bg-muted"
        >
            Go Home
        </a>
    </div>
@endsection
