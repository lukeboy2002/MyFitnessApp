{{-- Badge-medaille in de kleur van het niveau (brons t/m diamant), grijs als nog niet behaald. <x-medal :badge="$badge" :level="2" size="sm" /> --}}
@props(['badge', 'level' => null, 'size' => 'md'])

@php
    $tier = $badge->tier($level);
    $colors = [
        'brons' => 'from-amber-600 to-amber-800 text-white',
        'zilver' => 'from-slate-300 to-slate-500 text-white',
        'goud' => 'from-accent-300 to-accent-500 text-brand-950',
        'platina' => 'from-brand-500 to-brand-800 text-white',
        'diamant' => 'from-fuchsia-400 to-violet-600 text-white',
    ];
@endphp

<span title="{{ $tier ? ucfirst($tier) : 'Nog niet behaald' }}" {{ $attributes->class([
    'grid shrink-0 place-items-center rounded-2xl',
    'size-10' => $size === 'sm', 'size-14' => $size === 'md',
    'bg-linear-to-br shadow-sm '.($colors[$tier] ?? '') => $tier,
    'bg-slate-100 text-slate-400 dark:bg-white/10 dark:text-slate-500' => ! $tier,
]) }}>
    <x-icon :name="$badge->icon" @class(['size-5' => $size === 'sm', 'size-7' => $size === 'md']) />
</span>
