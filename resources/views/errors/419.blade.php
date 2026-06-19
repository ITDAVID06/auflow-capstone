@extends('errors.layout')

@section('title', 'Session Expired')

@section('content')
    <div class="space-y-6 text-center">
        <p class="text-sm font-medium uppercase tracking-[0.12em] text-muted-foreground">419</p>
        <h1 class="text-3xl font-semibold">Session Expired</h1>
        <p class="mx-auto max-w-xl text-sm text-muted-foreground">
            Your session has expired for security reasons. Please sign in again.
        </p>
        <a
            href="{{ route('login') }}"
            class="inline-flex items-center justify-center rounded-md border border-border px-4 py-2 text-sm font-medium transition hover:bg-muted"
        >
            Go to Login
        </a>
    </div>
@endsection
