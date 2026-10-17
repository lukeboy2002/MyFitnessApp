{{--
    Lijngrafiek, één reeks, oudste links. Hover of tik op een punt voor de waarde.
    <x-chart.line :points="[['date' => $carbon, 'value' => 52.5], ...]" unit="kg" />
--}}
@props(['points' => [], 'unit' => null])

@php
    $points = collect($points)->values();
    $values = $points->pluck('value');
    $n = fn ($v) => \App\Models\WorkoutSession::formatNumber($v);

    // Y-as met wat ruimte boven en onder, zodat de lijn niet tegen de rand plakt
    $low = $values->min();
    $high = $values->max();
    $pad = max(($high - $low) * 0.15, $high * 0.05, 0.5);
    $min = max(0, $low - $pad);
    $max = $high + $pad;

    $x = fn ($i) => $points->count() > 1 ? $i / ($points->count() - 1) * 100 : 50;
    $y = fn ($v) => 100 - ($v - $min) / ($max - $min) * 100;
    $line = $points->map(fn ($p, $i) => round($x($i), 2).','.round($y($p['value']), 2))->implode(' ');
@endphp

<div x-data="{ active: null }" {{ $attributes->merge(['class' => 'flex gap-2']) }}>
    <div class="flex h-40 flex-col justify-between py-0 text-right text-[11px] font-semibold tabular-nums text-slate-500 dark:text-slate-400">
        <span class="-mt-2">{{ $n(round($max, 1)) }}</span>
        <span>{{ $n(round(($max + $min) / 2, 1)) }}</span>
        <span class="-mb-2">{{ $n(round($min, 1)) }}</span>
    </div>

    <div class="min-w-0 flex-1">
        <div class="relative h-40 border-b border-slate-200 dark:border-line-dark">
            <div class="pointer-events-none absolute inset-0 flex flex-col justify-between">
                <span class="border-t border-slate-100 dark:border-white/5"></span>
                <span class="border-t border-slate-100 dark:border-white/5"></span>
                <span></span>
            </div>

            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible text-brand-600 dark:text-brand-400" aria-hidden="true">
                <polygon points="0,100 {{ $line }} 100,100" class="fill-current opacity-10" @if ($points->count() === 1) hidden @endif />
                <polyline points="{{ $line }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            </svg>

            @foreach ($points as $i => $point)
                <button type="button" x-on:mouseenter="active = {{ $i }}" x-on:mouseleave="active = null" x-on:focus="active = {{ $i }}" x-on:blur="active = null"
                        x-on:click="active = active === {{ $i }} ? null : {{ $i }}"
                        class="group absolute grid size-7 -translate-x-1/2 -translate-y-1/2 place-items-center focus:outline-none"
                        style="left: {{ $x($i) }}%; top: {{ $y($point['value']) }}%"
                        aria-label="{{ $point['date']->translatedFormat('j M Y') }}: {{ $n($point['value']) }} {{ $unit }}">
                    <span class="size-2.5 rounded-full bg-brand-600 ring-2 ring-white transition group-hover:scale-150 dark:bg-brand-400 dark:ring-panel-dark"
                          :class="active === {{ $i }} && 'scale-150'"></span>

                    <span x-show="active === {{ $i }}" x-cloak
                          @class(['absolute bottom-full z-10 mb-1 w-max rounded-lg bg-ink px-2.5 py-1.5 text-left text-xs text-white shadow-lg dark:bg-white dark:text-ink',
                                  'left-0' => $x($i) < 20, 'right-0' => $x($i) > 80, 'left-1/2 -translate-x-1/2' => $x($i) >= 20 && $x($i) <= 80])>
                        <span class="block font-bold">{{ $n($point['value']) }} {{ $unit }}</span>
                        {{ $point['date']->translatedFormat('j M Y') }}
                    </span>
                </button>
            @endforeach
        </div>

        <div class="mt-1.5 flex justify-between text-[11px] font-semibold text-slate-500 dark:text-slate-400">
            <span>{{ $points->first()['date']->translatedFormat('j M') }}</span>
            @if ($points->count() > 1)
                <span>{{ $points->last()['date']->translatedFormat('j M') }}</span>
            @endif
        </div>
    </div>
</div>
