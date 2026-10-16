{{--
    <x-alert type="success">Profiel opgeslagen.</x-alert>  types: info, success, warning, danger
    - success en info: verdwijnen na 15 seconden en hebben een sluitknop
    - danger: blijft staan tot je hem sluit
    - warning: vaste mededeling (bv. "gearchiveerd"), zonder sluitknop
    Zelf instellen: :timeout="0" (niet automatisch verdwijnen), :dismissible="false" (geen sluitknop)
--}}
@props(['type' => 'info', 'timeout' => null, 'dismissible' => null])

@php
    $styles = [
        'info' => ['bg-brand-50 text-brand-800 ring-brand-200 dark:bg-brand-500/10 dark:text-brand-200 dark:ring-brand-500/30', 'info'],
        'success' => ['bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/30', 'check'],
        'warning' => ['bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30', 'alert'],
        'danger' => ['bg-red-50 text-red-800 ring-red-200 dark:bg-red-500/10 dark:text-red-200 dark:ring-red-500/30', 'alert'],
    ][$type] ?? ['', 'info'];

    $timeout ??= in_array($type, ['success', 'info']) ? 15 : 0;
    $dismissible ??= $type !== 'warning';
@endphp

<div role="status"
     @if ($dismissible || $timeout)
         x-data="{ show: true }" x-show="show"
         x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 -translate-y-1"
         @if ($timeout) x-init="setTimeout(() => show = false, {{ $timeout * 1000 }})" @endif
     @endif
     {{ $attributes->merge(['class' => 'flex gap-3 rounded-xl px-4 py-3 text-sm font-medium ring-1 ring-inset '.$styles[0]]) }}>
    <x-icon :name="$styles[1]" class="mt-0.5 size-4.5 shrink-0" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
    @if ($dismissible)
        <button type="button" x-on:click="show = false" aria-label="Sluiten"
                class="-my-1 -mr-2 grid size-7 shrink-0 place-items-center rounded-lg opacity-60 transition hover:bg-black/5 hover:opacity-100 dark:hover:bg-white/10">
            <x-icon name="x" class="size-4" />
        </button>
    @endif
</div>
