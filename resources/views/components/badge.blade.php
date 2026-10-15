{{--
    <x-badge color="cardio">Cardio</x-badge>
    <x-badge :hex="$muscleGroup->color">{{ $muscleGroup->name }}</x-badge>   (eigen kleur per spiergroep)
--}}
@props(['color' => 'brand', 'hex' => null, 'icon' => null])

@php
    $colors = [
        'brand' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300',
        'fire' => 'bg-orange-50 text-fire-600 dark:bg-fire-500/15 dark:text-fire-400',
        'cardio' => 'bg-sky-50 text-cardio dark:bg-sky-400/15 dark:text-sky-300',
        'strength' => 'bg-orange-50 text-strength dark:bg-orange-400/15 dark:text-orange-300',
        'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300',
        'danger' => 'bg-red-50 text-red-700 dark:bg-red-400/15 dark:text-red-300',
        'gray' => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold '.($hex ? '' : ($colors[$color] ?? $colors['brand']))]) }}
      @if ($hex) style="background-color: color-mix(in srgb, {{ $hex }} 15%, transparent); color: {{ $hex }}" @endif>
    @if ($icon) <x-icon :name="$icon" class="size-3.5" /> @endif
    {{ $slot }}
</span>
