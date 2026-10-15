{{--
    Layout voor gasten (login, registreren, wachtwoord vergeten, e-mail verifiëren, 2FA).
    Gebruik: <x-layouts.guest title="Inloggen" subtitle="Welkom terug"> ... <x-slot:footer>...</x-slot:footer></x-layouts.guest>
--}}
@props(['title' => null, 'subtitle' => null])

    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.head', ['title' => $title])
</head>
<body class="min-h-full bg-canvas font-sans text-[15px] text-ink antialiased dark:bg-canvas-dark dark:text-slate-100">

{{-- Blauwe band met de sfeer van het logo --}}
<div
    class="absolute inset-x-0 top-0 h-80 overflow-hidden rounded-b-[2.5rem] bg-linear-to-br from-brand-600 via-brand-700 to-brand-950"
    aria-hidden="true">
    <div class="absolute -right-16 -top-24 size-72 rounded-full bg-accent-400/25 blur-3xl"></div>
    <div class="absolute -left-20 top-32 size-64 rounded-full bg-fire-500/20 blur-3xl"></div>
</div>

<div class="absolute right-[max(1rem,env(safe-area-inset-right))] top-[calc(env(safe-area-inset-top)+1rem)] text-white">
    <x-theme-toggle/>
</div>

<main
    class="relative flex min-h-dvh flex-col items-center px-[max(1rem,env(safe-area-inset-left))] pb-[calc(env(safe-area-inset-bottom)+2.5rem)] pt-[calc(env(safe-area-inset-top)+2.5rem)] sm:justify-center">
    <a href="{{ url('/') }}" class="mb-6 flex flex-col items-center text-white">
        <img src="{{ asset('storage/assets/logo.png') }}" alt="{{ config('app.name') }}"
             class="size-28 drop-shadow-xl sm:size-32">
    </a>

    <div
        class="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl shadow-brand-900/10 ring-1 ring-slate-200/80 sm:p-8 dark:bg-panel-dark dark:shadow-black/30 dark:ring-line-dark">
        @if ($title)
            <div class="mb-6">
                <h1 class="font-display text-3xl font-extrabold uppercase leading-none">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-2 text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                @endif
            </div>
        @endif

        @if (session('status'))
            <x-alert type="success" class="mb-5">{{ session('status') }}</x-alert>
        @endif

        {{ $slot }}
    </div>

    @isset($footer)
        <div class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">{{ $footer }}</div>
    @endisset
</main>
@livewireScripts
</body>
</html>
