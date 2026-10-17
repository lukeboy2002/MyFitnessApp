{{--
    Staafgrafiek, één reeks. Hover of tik op een staaf voor de waarde.
    <x-chart.bars :bars="[['label' => 'wk 41', 'value' => 95, 'tip' => '1u 35m · 3 trainingen', 'current' => true], ...]" :goal="300" goal-label="Doel 5u" />
--}}
@props(['bars' => [], 'goal' => null, 'goalLabel' => null])

@php
    $max = max(1, $goal ?? 0, ...array_map(fn ($bar) => $bar['value'], $bars ?: [['value' => 0]]));
@endphp

<div x-data="{ active: null }" {{ $attributes->merge(['class' => 'relative']) }}>
    <div class="relative flex h-40 items-end gap-1 border-b border-slate-200 sm:gap-2 dark:border-line-dark">
        @if ($goal)
            <div class="pointer-events-none absolute inset-x-0 border-t border-dashed border-slate-400 dark:border-slate-500" style="bottom: {{ $goal / $max * 100 }}%">
                @if ($goalLabel)
                    <span class="absolute right-0 -top-5 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $goalLabel }}</span>
                @endif
            </div>
        @endif

        @foreach ($bars as $i => $bar)
            <button type="button" x-on:mouseenter="active = {{ $i }}" x-on:mouseleave="active = null" x-on:focus="active = {{ $i }}" x-on:blur="active = null"
                    x-on:click="active = active === {{ $i }} ? null : {{ $i }}"
                    class="group relative flex h-full flex-1 items-end justify-center focus:outline-none" aria-label="{{ $bar['label'] }}: {{ $bar['tip'] }}">
                <span @class(['w-full max-w-8 rounded-t transition',
                              'bg-brand-600 dark:bg-brand-400' => $bar['current'] ?? false,
                              'bg-brand-300 group-hover:bg-brand-400 dark:bg-brand-500/50 dark:group-hover:bg-brand-500/70' => ! ($bar['current'] ?? false)])
                      style="height: {{ $bar['value'] > 0 ? max(2, $bar['value'] / $max * 100) : 0 }}%"></span>

                <span x-show="active === {{ $i }}" x-cloak
                      @class(['absolute bottom-full z-10 mb-2 w-max max-w-44 rounded-lg bg-ink px-2.5 py-1.5 text-left text-xs text-white shadow-lg dark:bg-white dark:text-ink',
                              'left-0' => $i < 2, 'right-0' => $i >= count($bars) - 2, 'left-1/2 -translate-x-1/2' => $i >= 2 && $i < count($bars) - 2])>
                    <span class="block font-bold">{{ $bar['label'] }}</span>
                    {{ $bar['tip'] }}
                </span>
            </button>
        @endforeach
    </div>

    <div class="mt-1.5 flex gap-1 text-center text-[11px] font-semibold text-slate-500 sm:gap-2 dark:text-slate-400">
        @foreach ($bars as $i => $bar)
            <span class="flex-1 truncate">{{ $i % 2 === (count($bars) - 1) % 2 ? $bar['short'] ?? $bar['label'] : '' }}</span>
        @endforeach
    </div>
</div>
