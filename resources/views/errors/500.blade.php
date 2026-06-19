@extends('errors.layout')

@section('title', 'Server Error')

@section('content')
    <div class="space-y-6 text-center">
        <p class="text-sm font-medium uppercase tracking-[0.12em] text-muted-foreground">500</p>
        <h1 class="text-3xl font-semibold">Something went wrong on our end</h1>
        <p class="mx-auto max-w-xl text-sm text-muted-foreground">
            We could not complete your request right now. Please try again in a moment.
        </p>
        <a
            href="{{ url('/') }}"
            class="inline-flex items-center justify-center rounded-md border border-border px-4 py-2 text-sm font-medium transition hover:bg-muted"
        >
            Go Home
        </a>
    </div>
@endsection
