{{--
    Hartslag met zone, bv. "zone 3 · Aeroob · 84% van max".
    <x-heart-rate-zone :bpm="$log->avg_heart_rate" :zones="$zones" />  ($zones = HeartRateZones::forUser(...), mag null zijn)
--}}
@props(['bpm' => null, 'zones' => null])

@if ($zones && ($zone = $zones->zoneFor($bpm)))
    <span {{ $attributes->class([
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
        'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200' => $zone['number'] === 1,
        'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300' => $zone['number'] === 2,
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $zone['number'] === 3,
        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300' => $zone['number'] === 4,
        'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' => $zone['number'] === 5,
    ]) }} title="{{ $zone['percent'] }}% van je max. hartslag ({{ $zones->max }} bpm)">
        <x-icon name="heart" class="size-3.5" /> Zone {{ $zone['number'] }} · {{ $zone['name'] }} · {{ $zone['percent'] }}%
    </span>
@endif
