{{--
    Layout voor ingelogde gebruikers.
    Gebruik: <x-layouts.app title="Workouts"> ... </x-layouts.app>
    Livewire full-page componenten: #[Layout('components.layouts.app')] (standaard in Livewire 3)

    lg (≥1024px): sidebar met iconen + tekst (w-64)
    md (≥768px):  sidebar met alleen iconen (w-20)
    mobiel:       blauwe topbalk + vaste menubalk onderaan
--}}
@props(['title' => null])

@php
    $link = fn (string $route) => Route::has($route) ? route($route) : '#';

    // Hoofdmenu (sidebar). 'active' = patroon voor request()->routeIs()
    $nav = [
        ['route' => 'dashboard',           'active' => 'dashboard',        'icon' => 'home',     'label' => 'Dashboard'],
        ['route' => 'workouts.index',      'active' => 'workouts.*',       'icon' => 'dumbbell', 'label' => 'Workouts'],
        ['route' => 'exercises.index',     'active' => 'exercises.*',      'icon' => 'bolt',     'label' => 'Exercises'],
        ['route' => 'body-parts.index',    'active' => 'body-parts.*',     'icon' => 'body',     'label' => 'BodyParts'],
        ['route' => 'muscle-groups.index', 'active' => 'muscle-groups.*',  'icon' => 'arm',      'label' => 'MuscleGroups'],
        ['route' => 'muscles.index',       'active' => 'muscles.*',        'icon' => 'fiber',    'label' => 'Muscles'],
        ['route' => 'calendar',            'active' => 'calendar',         'icon' => 'calendar', 'label' => 'Calendar'],
        ['route' => 'progress.index',      'active' => 'progress.*',       'icon' => 'chart',    'label' => 'Progress'],
    ];

    // Mobiele menubalk (altijd onderaan)
    $mobile = [
        ['route' => 'dashboard',      'active' => 'dashboard',               'icon' => 'home',     'label' => 'Dashboard'],
        ['route' => 'workouts.index', 'active' => 'workouts.*',              'icon' => 'dumbbell', 'label' => 'Workouts'],
        ['route' => 'quickstart',     'active' => 'quickstart|sessions.*',   'icon' => 'play',     'label' => 'Quickstart', 'highlight' => true],
        ['route' => 'progress.index', 'active' => 'progress.*',              'icon' => 'chart',    'label' => 'Progress'],
        ['route' => 'profile.edit',   'active' => 'profile.*',               'icon' => 'user',     'label' => 'Profile'],
    ];

    // Op mobiel onder de drie puntjes rechtsboven
    $more = collect($nav)->whereNotIn('route', ['dashboard', 'workouts.index', 'progress.index']);

    $is = fn (string $pattern) => request()->routeIs(...explode('|', $pattern));
    // Tijdens en na een training de naam van de training (Quickstart-knop blijft wel actief)
    $current = $is('sessions.*') && $title
        ? $title
        : collect($nav)->merge($mobile)->first(fn ($i) => $is($i['active']))['label'] ?? $title;
@endphp

    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.head', ['title' => $title])
</head>
<body class="min-h-full bg-canvas font-sans text-[15px] text-ink antialiased dark:bg-canvas-dark dark:text-slate-100">

{{-- Sidebar: md en groter --}}
<aside
    class="fixed inset-y-0 left-0 z-30 hidden w-20 flex-col gap-1 overflow-y-auto bg-brand-700 px-3 py-4 text-white md:flex lg:w-64 lg:px-4 lg:py-5 dark:bg-brand-950">
    <a href="{{ $link('dashboard') }}" wire:navigate class="mb-3 flex justify-center lg:justify-start">
        <x-app-logo :text="false" class="lg:hidden"/>
        <x-app-logo class="hidden lg:flex"/>
    </a>

    <nav class="flex flex-col gap-1" aria-label="Hoofdmenu">
        @foreach ($nav as $item)
            <x-nav.sidebar-link :href="$link($item['route'])" :icon="$item['icon']" :active="$is($item['active'])">
                {{ $item['label'] }}
            </x-nav.sidebar-link>
        @endforeach
    </nav>

    <a href="{{ $link('quickstart') }}" wire:navigate title="Quickstart"
       class="my-3 flex items-center justify-center gap-3 rounded-2xl bg-linear-to-br from-accent-400 to-fire-500 px-3 py-3 font-bold text-white shadow-lg shadow-black/20 transition hover:brightness-105 lg:justify-start lg:px-3.5">
        <x-icon name="play" class="size-5.5"/>
        <span class="hidden lg:inline">Quickstart</span>
    </a>

    <div class="mt-auto flex flex-col gap-1 border-t border-white/10 pt-3">
        <x-nav.sidebar-link :href="$link('profile.edit')" icon="user" :active="$is('profile.*')">Profiel
        </x-nav.sidebar-link>
        <x-theme-toggle variant="sidebar"/>
        <form method="POST" action="{{ $link('logout') }}">
            @csrf
            <button type="submit" title="Uitloggen"
                    class="flex w-full items-center justify-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-brand-200 transition hover:bg-white/10 hover:text-white lg:justify-start lg:px-3.5">
                <x-icon name="logout" class="size-5.5"/>
                <span class="hidden lg:inline">Uitloggen</span>
            </button>
        </form>
        <div class="mt-2 hidden items-center gap-3 rounded-xl bg-white/5 p-2.5 lg:flex">
            <x-avatar :user="auth()->user()"/>
            <div class="min-w-0 text-sm">
                <p class="truncate font-semibold">{{ auth()->user()?->name }}</p>
                <p class="truncate text-brand-200">{{ auth()->user()?->email }}</p>
            </div>
        </div>
    </div>
</aside>

<div class="flex min-h-dvh flex-col md:pl-20 lg:pl-64">

    {{-- Topbalk: alleen mobiel --}}
    <header x-data="{ more: false }" x-on:keydown.escape.window="more = false"
            class="sticky top-0 z-20 flex items-center gap-2 bg-brand-700 pb-2 pl-[max(1rem,env(safe-area-inset-left))] pr-[max(.5rem,env(safe-area-inset-right))] pt-[calc(env(safe-area-inset-top)+.5rem)] text-white shadow-md md:hidden dark:bg-brand-950">
        <a href="{{ $link('dashboard') }}" wire:navigate>
            <img src="{{ asset('storage/assets/logo.png') }}" alt="{{ config('app.name') }}" class="size-10"></a>
        <span
            class="min-w-0 flex-1 truncate font-display text-xl font-extrabold uppercase tracking-wide">{{ $current }}</span>
        <form method="POST" action="{{ $link('logout') }}">
            @csrf
            <button type="submit" aria-label="Uitloggen" title="Uitloggen"
                    class="grid size-11 place-items-center rounded-xl transition hover:bg-white/15">
                <x-icon name="logout" class="size-6"/>
            </button>
        </form>
        {{--        <x-theme-toggle/>--}}
        {{--        <button type="button" x-on:click="more = ! more" :aria-expanded="more" aria-label="Meer"--}}
        {{--                class="grid size-11 place-items-center rounded-xl transition hover:bg-white/15">--}}
        {{--            <x-icon name="dots" class="size-6"/>--}}
        {{--        </button>--}}

        {{--        <div x-show="more" x-cloak x-transition.origin.top.right x-on:click.outside="more = false"--}}
        {{--             class="absolute right-3 top-full mt-2 w-60 rounded-2xl bg-white p-1.5 text-ink shadow-xl ring-1 ring-slate-200 dark:bg-panel-dark dark:text-slate-100 dark:ring-line-dark">--}}
        {{--            @foreach ($more as $item)--}}
        {{--                <a href="{{ $link($item['route']) }}" wire:navigate--}}
        {{--                    @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold hover:bg-slate-100 dark:hover:bg-white/5',--}}
        {{--                            'text-brand-700 dark:text-brand-300' => $is($item['active'])])>--}}
        {{--                    <x-icon :name="$item['icon']"/> {{ $item['label'] }}--}}
        {{--                </a>--}}
        {{--            @endforeach--}}
        {{--            <form method="POST" action="{{ $link('logout') }}"--}}
        {{--                  class="mt-1 border-t border-slate-200 pt-1 dark:border-line-dark">--}}
        {{--                @csrf--}}
        {{--                <button--}}
        {{--                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">--}}
        {{--                    <x-icon name="logout"/>--}}
        {{--                    Uitloggen--}}
        {{--                </button>--}}
        {{--            </form>--}}
        {{--        </div>--}}
    </header>

    {{-- Inhoud. pb-28 op mobiel zodat niets achter de menubalk valt --}}
    <main
        class="flex-1 pb-[calc(env(safe-area-inset-bottom)+7rem)] pl-[max(1rem,env(safe-area-inset-left))] pr-[max(1rem,env(safe-area-inset-right))] pt-5 md:px-7 md:pb-10 md:pt-7 lg:px-10 lg:pt-8">
        <div class="mx-auto max-w-6xl">
            @if (session('status'))
                <x-alert type="success" class="mb-5">{{ session('status') }}</x-alert>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>

{{-- Mobiele menubalk: altijd zichtbaar onderaan --}}
<nav aria-label="Menu"
     class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 items-end border-t border-slate-200 bg-white/95 px-1 pt-1.5 pb-[calc(env(safe-area-inset-bottom)+.25rem)] backdrop-blur md:hidden dark:border-line-dark dark:bg-panel-dark/95">
    @foreach ($mobile as $item)
        <x-nav.bottom-link :href="$link($item['route'])" :icon="$item['icon']" :active="$is($item['active'])"
                           :highlight="$item['highlight'] ?? false">
            {{ $item['label'] }}
        </x-nav.bottom-link>
    @endforeach
</nav>

@livewireScripts
</body>
</html>
