@php
    use App\Models\WorkoutSession;
    use App\Support\TrainingStats;

    $n = fn ($v) => WorkoutSession::formatNumber($v);
    $maxGroup = max(1, $groups->max('count') ?? 1);
@endphp

<div>
    <x-page-header title="Progress" description="Je trainingen in cijfers, en per oefening hoe je vooruitgaat." />

    {{-- Periode --}}
    <div class="mb-5 inline-flex rounded-full bg-white p-1 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-line-dark">
        @foreach (\App\Livewire\Progress\Index::PERIODS as $key => $label)
            <button type="button" wire:click="$set('period', '{{ $key }}')"
                    @class(['rounded-full px-4 py-2 text-sm font-semibold transition',
                            'bg-brand-700 text-white dark:bg-brand-500' => $period === $key,
                            'text-slate-600 hover:text-ink dark:text-slate-300 dark:hover:text-white' => $period !== $key])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            'Trainingstijd' => TrainingStats::hours($totals['seconds']),
            'Trainingen' => $totals['count'],
            'Sets' => $totals['sets'],
            'Volume kracht' => TrainingStats::kilos($totals['volume']).' kg',
        ] as $label => $value)
            <x-card>
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">{{ $label }}</p>
                <p class="mt-1 font-display text-[28px] font-extrabold whitespace-nowrap tabular-nums sm:text-4xl">{{ $value }}</p>
            </x-card>
        @endforeach
    </div>

    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <x-card title="Per spiergroep" description="Afgevinkte sets (cardio: afgeronde oefeningen)">
            @if ($groups->isEmpty())
                <p class="text-slate-500 dark:text-slate-400">Nog niets getraind in deze periode.</p>
            @else
                <ul class="grid gap-2.5">
                    @foreach ($groups as $group)
                        <li class="grid grid-cols-[7.5rem_1fr_2rem] items-center gap-3 text-sm">
                            <span class="truncate font-semibold">{{ $group['name'] }}</span>
                            <span class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                <span class="block h-full rounded-full" style="width: {{ $group['count'] / $maxGroup * 100 }}%; background-color: {{ $group['color'] ?? 'var(--color-brand-600)' }}"></span>
                            </span>
                            <span class="text-right font-semibold tabular-nums">{{ $group['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Laatste 12 maanden" description="Trainingstijd per maand">
            <x-chart.bars class="mt-2" :bars="$months->map(fn ($month) => [
                'label' => ucfirst($month['start']->translatedFormat('F Y')),
                'short' => $month['start']->translatedFormat('M'),
                'value' => round($month['seconds'] / 60),
                'tip' => TrainingStats::hours($month['seconds']).' · '.$month['count'].' '.($month['count'] === 1 ? 'training' : 'trainingen'),
                'current' => $month['start']->isCurrentMonth(),
            ])->all()" />
        </x-card>
    </div>

    {{-- Oefeningen --}}
    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <h2 class="font-display text-2xl font-bold uppercase">Per oefening</h2>
        <x-form.input wire:model.live.debounce.300ms="search" icon="search" placeholder="Zoek een oefening…" aria-label="Zoeken" class="sm:w-72" />
    </div>

    <x-card :padding="false" class="overflow-hidden">
        @if ($this->exercises->isEmpty())
            <p class="px-5 py-5 text-slate-500 sm:px-6 dark:text-slate-400">
                {{ $search ? 'Geen oefening gevonden.' : 'Nog geen oefeningen gedaan. Na je eerste training zie je hier je voortgang.' }}
            </p>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-line-dark">
                @foreach ($this->exercises as $exercise)
                    <li wire:key="ex-{{ $exercise->id }}">
                        <a href="{{ route('progress.exercise', $exercise) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50 sm:px-5 dark:hover:bg-white/[.03]">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300">
                                <x-icon :name="$exercise->type->icon()" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $exercise->name }}</span>
                                <span class="block text-sm text-slate-500 dark:text-slate-400">
                                    {{ $exercise->times }}× gedaan · laatst {{ $exercise->last_at->translatedFormat('j M Y') }}
                                </span>
                            </span>
                            <x-badge :color="$exercise->type->color()" class="hidden sm:inline-flex">{{ $exercise->type->label() }}</x-badge>
                            <x-icon name="chevron-right" class="size-5 text-slate-400" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
