{{--
    Opmaak van de foutpagina's, in de stijl van de inlogpagina's.
    Bewust zonder database, sessie, ingelogde gebruiker of Livewire: dan kan de foutpagina zelf niet ook nog misgaan.
    Gebruik: @extends('errors.layout') met @section('code'), 'title', 'message' en eventueel 'actions'.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#00468c">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('icons/favicon-32.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=barlow:400,500,600,700|barlow-condensed:600,700,800&display=swap">
    <script>
        (() => {
            const mode = localStorage.getItem('theme') ?? 'system';
            const dark = mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-canvas font-sans text-[15px] text-ink antialiased dark:bg-canvas-dark dark:text-slate-100">

<div class="absolute inset-x-0 top-0 h-80 overflow-hidden rounded-b-[2.5rem] bg-linear-to-br from-brand-600 via-brand-700 to-brand-950" aria-hidden="true">
    <div class="absolute -right-16 -top-24 size-72 rounded-full bg-accent-400/25 blur-3xl"></div>
    <div class="absolute -left-20 top-32 size-64 rounded-full bg-fire-500/20 blur-3xl"></div>
</div>

<main class="relative flex min-h-dvh flex-col items-center px-[max(1rem,env(safe-area-inset-left))] pb-[calc(env(safe-area-inset-bottom)+2.5rem)] pt-[calc(env(safe-area-inset-top)+2.5rem)] sm:justify-center">
    <a href="{{ url('/') }}" class="mb-6 flex flex-col items-center text-white">
        <img src="{{ asset('icons/icon-192.png') }}" alt="{{ config('app.name') }}" class="size-24 rounded-3xl drop-shadow-xl sm:size-28">
    </a>

    <div class="w-full max-w-md rounded-3xl bg-white p-6 text-center shadow-xl shadow-brand-900/10 ring-1 ring-slate-200/80 sm:p-8 dark:bg-panel-dark dark:shadow-black/30 dark:ring-line-dark">
        <p class="font-display text-7xl font-extrabold leading-none text-transparent bg-linear-to-br from-accent-400 to-fire-500 bg-clip-text">@yield('code')</p>
        <h1 class="mt-3 font-display text-3xl font-extrabold uppercase leading-none">@yield('title')</h1>
        <p class="mt-3 text-slate-600 dark:text-slate-300">@yield('message')</p>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
            @hasSection('actions')
                @yield('actions')
            @else
                <x-button variant="secondary" type="button" icon="chevron-left" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">Terug</x-button>
                <x-button :href="url('/')" icon="home">Naar dashboard</x-button>
            @endif
        </div>
    </div>
</main>
</body>
</html>
