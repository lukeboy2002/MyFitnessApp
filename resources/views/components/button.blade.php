{{--
    <x-button>Opslaan</x-button>
    <x-button variant="fire" icon="play" href="{{ route('quickstart') }}">Quickstart</x-button>
    <x-button variant="danger" size="sm" wire:click="delete" loading="delete">Verwijderen</x-button>
    Varianten: primary, fire, secondary, ghost, danger · Maten: sm, md, lg
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'type' => 'submit',
    'loading' => null,   // naam van de Livewire-actie, toont spinner tijdens laden
    'block' => false,
])

@php
    $variants = [
        'primary' => 'bg-brand-700 text-white shadow-sm hover:bg-brand-800 dark:bg-brand-500 dark:hover:bg-brand-400',
        'fire' => 'bg-linear-to-br from-accent-400 to-fire-500 text-white shadow-sm shadow-fire-500/30 hover:brightness-105',
        'secondary' => 'bg-white text-ink ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:bg-white/5 dark:text-white dark:ring-line-dark dark:hover:bg-white/10',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-ink dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-400',
    ];
    $sizes = [
        'sm' => 'min-h-9 gap-1.5 rounded-lg px-3 text-sm',
        'md' => 'min-h-11 gap-2 rounded-xl px-4 text-[15px]',
        'lg' => 'min-h-13 gap-2.5 rounded-2xl px-6 text-base',
    ];
    $classes = 'inline-flex items-center justify-center font-semibold transition select-none
        focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-400
        disabled:pointer-events-none disabled:opacity-50 active:scale-[.98] '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']).($block ? ' w-full' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" class="size-[1.15em]" /> @endif
        {{ $slot }}
        @if ($iconRight) <x-icon :name="$iconRight" class="size-[1.15em]" /> @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}
            @if ($loading) wire:loading.attr="disabled" wire:target="{{ $loading }}" @endif>
        @if ($loading)
            <svg wire:loading wire:target="{{ $loading }}" class="size-[1.15em] animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25"/>
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
        @endif
        @if ($icon && $loading)
            <span wire:loading.remove wire:target="{{ $loading }}" class="contents"><x-icon :name="$icon" class="size-[1.15em]" /></span>
        @elseif ($icon)
            <x-icon :name="$icon" class="size-[1.15em]" />
        @endif
        {{ $slot }}
        @if ($iconRight) <x-icon :name="$iconRight" class="size-[1.15em]" /> @endif
    </button>
@endif
