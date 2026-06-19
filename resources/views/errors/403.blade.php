@extends('errors.layout')

@section('title', 'Access Denied')

@section('content')
    @php
        $previousUrl = url()->previous();
        $backUrl = $previousUrl !== url()->current() ? $previousUrl : url('/');
    @endphp

    <div class="space-y-6 text-center">
        <p class="text-sm font-medium uppercase tracking-[0.12em] text-muted-foreground">403</p>
        <h1 class="text-3xl font-semibold">Access Denied</h1>
        <p class="mx-auto max-w-xl text-sm text-muted-foreground">
            You do not have permission to view this page. If you think this is a mistake,
            contact support.
        </p>
        <a
            href="{{ $backUrl }}"
            class="inline-flex items-center justify-center rounded-md border border-border px-4 py-2 text-sm font-medium transition hover:bg-muted"
        >
            Go Back
        </a>
    </div>
@endsection
