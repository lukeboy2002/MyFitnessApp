{{-- <x-alert type="success">Profiel opgeslagen.</x-alert>  types: info, success, warning, danger --}}
@props(['type' => 'info'])

@php
    $styles = [
        'info' => ['bg-brand-50 text-brand-800 ring-brand-200 dark:bg-brand-500/10 dark:text-brand-200 dark:ring-brand-500/30', 'info'],
        'success' => ['bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/30', 'check'],
        'warning' => ['bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30', 'alert'],
        'danger' => ['bg-red-50 text-red-800 ring-red-200 dark:bg-red-500/10 dark:text-red-200 dark:ring-red-500/30', 'alert'],
    ][$type] ?? ['', 'info'];
@endphp

<div role="status" {{ $attributes->merge(['class' => 'flex gap-3 rounded-xl px-4 py-3 text-sm font-medium ring-1 ring-inset '.$styles[0]]) }}>
    <x-icon :name="$styles[1]" class="mt-0.5 size-4.5" />
    <div class="min-w-0">{{ $slot }}</div>
</div>
