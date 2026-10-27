@php
    use App\Enums\ExerciseType;
    use App\Models\CardioLog;
    use App\Models\WorkoutSession;

    $n = fn ($v) => WorkoutSession::formatNumber($v);
    $type = $exercise->type;
    $rows = $progress->rows;
    [$measure, $unit] = $progress->measure();
    $points = $progress->points();
    $bests = collect($records->list())->except('Vorige keer');

    // Cardio: lege kolommen weglaten
    $has = fn (string $key) => $rows->contains(fn ($row) => filled($row[$key] ?? null) || filled($row['log']->{$key} ?? null));

    $th = 'px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wide text-slate-500 first:pl-5 last:pr-5 dark:text-slate-400';
    $td = 'px-3 py-2.5 whitespace-nowrap tabular-nums first:pl-5 last:pr-5';
@endphp

<div>
    <a href="{{ route('progress.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
        <x-icon name="chevron-right" class="size-4 rotate-180" /> Progress
    </a>

    <x-page-header :title="$exercise->name" :description="$type->label().' · '.$rows->count().'× gedaan'">
        <x-button variant="secondary" :href="route('exercises.show', $exercise)" wire:navigate>Oefening bekijken</x-button>
    </x-page-header>

    @if ($rows->isEmpty())
        <x-card>
            <p class="text-slate-500 dark:text-slate-400">Je hebt deze oefening nog niet gedaan in een afgeronde training. Daarna zie je hier je voortgang.</p>
        </x-card>
    @else
        <div class="mb-5 grid gap-4 lg:grid-cols-[18rem_1fr]">
            <x-card title="Records">
                <dl class="grid gap-3">
                    @foreach ($bests as $label => $value)
                        <div class="flex items-center gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-accent-300/30 text-accent-500 dark:bg-accent-400/15">
                                <x-icon name="trophy" class="size-4.5" />
                            </span>
                            <div>
                                <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                                <dd class="font-semibold tabular-nums">{{ $value }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card :title="$measure" :description="'Per training, in '.$unit">
                @if ($points->count() > 1)
                    <x-chart.line class="mt-2" :points="$points" :unit="$unit" />
                @else
                    <p class="text-slate-500 dark:text-slate-400">Na je tweede keer zie je hier een grafiek.</p>
                @endif
            </x-card>
        </div>

        <x-card title="Alle keren" :padding="false" class="overflow-hidden">
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y border-slate-100 bg-slate-50 dark:border-line-dark dark:bg-white/[.03]">
                        <tr>
                            <th class="{{ $th }}">Datum</th>
                            @switch($type)
                                @case(ExerciseType::Strength)
                                    <th class="{{ $th }}">Sets</th>
                                    <th class="{{ $th }}">Beste set</th>
                                    <th class="{{ $th }} text-right">Volume</th>
                                    <th class="{{ $th }} text-right">1RM</th>
                                    @break
                                @case(ExerciseType::Bodyweight)
                                    <th class="{{ $th }}">Sets</th>
                                    <th class="{{ $th }} text-right">Totaal reps</th>
                                    <th class="{{ $th }} text-right">Meeste reps</th>
                                    <th class="{{ $th }} text-right">Extra</th>
                                    @break
                                @case(ExerciseType::Duration)
                                    <th class="{{ $th }}">Sets</th>
                                    <th class="{{ $th }} text-right">Totale tijd</th>
                                    <th class="{{ $th }} text-right">Langste set</th>
                                    @break
                                @default
                                    <th class="{{ $th }} text-right">Tijd</th>
                                    <th class="{{ $th }}">Instelling</th>
                                    <th class="{{ $th }} text-right">Afstand</th>
                                    @if ($has('fastest_km')) <th class="{{ $th }} text-right">Snelste km</th> @endif
                                    @if ($has('avg_watts')) <th class="{{ $th }} text-right">Watts</th> @endif
                                    @if ($has('mets')) <th class="{{ $th }} text-right">METs</th> @endif
                                    @if ($has('active_kcal') || $has('total_kcal')) <th class="{{ $th }} text-right">kcal ACT/TTL</th> @endif
                                    @if ($has('floors')) <th class="{{ $th }} text-right">Floors</th> @endif
                                    @if ($has('avg_heart_rate')) <th class="{{ $th }} text-right">Hartslag</th> @endif
                            @endswitch
                            <th class="{{ $th }} text-right">t.o.v. vorige</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-line-dark">
                        @foreach ($rows as $row)
                            <tr wire:key="row-{{ $loop->index }}" class="transition hover:bg-slate-50 dark:hover:bg-white/[.03]">
                                <td class="{{ $td }}">
                                    <a href="{{ route('sessions.summary', $row['session']) }}" wire:navigate class="font-semibold text-brand-700 hover:underline dark:text-brand-300">
                                        {{ $row['session']->started_at->translatedFormat('j M Y') }}
                                    </a>
                                </td>
                                @switch($type)
                                    @case(ExerciseType::Strength)
                                        <td class="{{ $td }}">{{ $row['sets'] }}</td>
                                        <td class="{{ $td }}">{{ $row['best'] }}</td>
                                        <td class="{{ $td }} text-right">{{ \App\Support\TrainingStats::kilos($row['volume']) }} kg</td>
                                        <td class="{{ $td }} text-right font-semibold">{{ $row['one_rep_max'] ? $n($row['one_rep_max']).' kg' : '–' }}</td>
                                        @break
                                    @case(ExerciseType::Bodyweight)
                                        <td class="{{ $td }}">{{ $row['sets'] }}</td>
                                        <td class="{{ $td }} text-right font-semibold">{{ $row['reps'] }}</td>
                                        <td class="{{ $td }} text-right">{{ $row['most_reps'] }}</td>
                                        <td class="{{ $td }} text-right">{{ $row['extra'] ? ($row['extra'] > 0 ? '+' : '').$n($row['extra']).' kg' : '–' }}</td>
                                        @break
                                    @case(ExerciseType::Duration)
                                        <td class="{{ $td }}">{{ $row['sets'] }}</td>
                                        <td class="{{ $td }} text-right font-semibold">{{ $row['total'] }} sec</td>
                                        <td class="{{ $td }} text-right">{{ $row['longest'] }} sec</td>
                                        @break
                                    @default
                                        @php($log = $row['log'])
                                        <td class="{{ $td }} text-right">{{ $log->duration_seconds ? WorkoutSession::clock($log->duration_seconds) : '–' }}</td>
                                        <td class="{{ $td }}">{{ $log->metric && filled($log->metric_value) ? $log->metric->format($log->metric_value) : '–' }}</td>
                                        <td class="{{ $td }} text-right font-semibold">{{ $log->distance_m ? $n(round($log->distance_m / 1000, 2)).' km' : '–' }}</td>
                                        @if ($has('fastest_km')) <td class="{{ $td }} text-right">{{ $row['fastest_km'] ? CardioLog::pace($row['fastest_km']) : '–' }}</td> @endif
                                        @if ($has('avg_watts')) <td class="{{ $td }} text-right">{{ $log->avg_watts ?? '–' }}</td> @endif
                                        @if ($has('mets')) <td class="{{ $td }} text-right">{{ filled($log->mets) ? $n($log->mets) : '–' }}</td> @endif
                                        @if ($has('active_kcal') || $has('total_kcal')) <td class="{{ $td }} text-right">{{ $log->active_kcal ?? '–' }} / {{ $log->total_kcal ?? '–' }}</td> @endif
                                        @if ($has('floors')) <td class="{{ $td }} text-right">{{ $log->floors ?? '–' }}</td> @endif
                                        @if ($has('avg_heart_rate')) <td class="{{ $td }} text-right">{{ $log->avg_heart_rate ? $log->avg_heart_rate.' bpm' : '–' }} @if ($zone = ($hrZones ??= \App\Support\HeartRateZones::forUser(auth()->user()))?->zoneFor($log->avg_heart_rate)) <span class="text-xs font-normal text-slate-500 dark:text-slate-400" title="{{ $zone['name'] }}">Z{{ $zone['number'] }}</span> @endif</td> @endif
                                @endswitch
                                <td class="{{ $td }} text-right">
                                    @if ($row['delta'] > 0)
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">▲ {{ $n($row['delta']) }} {{ $unit }}</span>
                                    @elseif ($row['delta'] < 0)
                                        <span class="font-semibold text-red-600 dark:text-red-400">▼ {{ $n(abs($row['delta'])) }} {{ $unit }}</span>
                                    @elseif ($row['delta'] === 0.0)
                                        <span class="text-slate-500 dark:text-slate-400">gelijk</span>
                                    @else
                                        <span class="text-slate-400">–</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
