@php($n = fn ($v) => \App\Models\WorkoutSession::formatNumber($v))

<div style="--c: {{ $session->color ?? '#1d63c5' }}">
    <div class="mb-6 overflow-hidden rounded-3xl bg-(--c) p-5 text-white shadow-lg sm:p-7">
        <p class="text-sm font-semibold uppercase tracking-wide text-white/80">Training afgerond · {{ $session->started_at->translatedFormat('l j F, H:i') }}</p>
        <h1 class="mt-1 font-display text-[34px] leading-none font-extrabold uppercase">{{ $session->name }}</h1>

        <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                'Tijd' => \App\Models\WorkoutSession::clock($session->elapsedSeconds()),
                'Sets' => $setsDone.($setsTotal && $setsDone < $setsTotal ? ' / '.$setsTotal : ''),
                'Volume' => $n($session->volume()).' kg',
                'Oefeningen' => $session->exercises->filter->isDone()->count().' / '.$session->exercises->count(),
            ] as $label => $value)
                <div class="rounded-2xl bg-white/15 p-3 backdrop-blur">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-white/75">{{ $label }}</dt>
                    <dd class="font-display text-2xl font-bold tabular-nums">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    @php($newRecords = $session->exercises->sum(fn ($item) => isset($records[$item->exercise_id])
        ? ($item->exercise->type->usesSets() ? $item->sets->filter(fn ($set) => $records[$item->exercise_id]->isRecordSet($set))->count() : count($records[$item->exercise_id]->cardioRecords($item->cardio)))
        : 0))
    @if ($newRecords)
        <p x-init="setTimeout(() => window.confetti?.(), 300)" class="mb-5 flex items-center gap-2 rounded-2xl bg-accent-300/30 px-4 py-3 font-semibold text-brand-950 dark:bg-accent-400/15 dark:text-accent-300">
            <x-icon name="trophy" class="size-5 text-accent-500" /> {{ $newRecords }} {{ $newRecords === 1 ? 'nieuw record' : 'nieuwe records' }}!
        </p>
    @endif

    <x-card :padding="false" class="overflow-hidden">
        <ol class="divide-y divide-slate-100 dark:divide-line-dark">
            @foreach ($session->exercises as $item)
                @php($type = $item->exercise->type)
                @php($done = $item->sets->where('is_completed', true))
                <li class="flex gap-4 px-4 py-3 sm:px-5">
                    <span @class(['mt-0.5 grid size-7 shrink-0 place-items-center rounded-full text-sm font-bold',
                                  'bg-emerald-500 text-white' => $item->isDone(),
                                  'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300' => ! $item->isDone()])>
                        @if ($item->isDone()) <x-icon name="check" class="size-4" /> @else {{ $loop->iteration }} @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $item->exercise->name }}</p>
                        @if ($type->usesSets())
                            @if ($done->isEmpty())
                                <p class="text-sm text-slate-500 dark:text-slate-400">Niet gedaan</p>
                            @else
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach ($done as $set)
                                        @php($isRecord = isset($records[$item->exercise_id]) && $records[$item->exercise_id]->isRecordSet($set))
                                        <span @class(['inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-sm font-semibold tabular-nums',
                                                      'bg-accent-300/40 text-brand-950 dark:bg-accent-400/20 dark:text-accent-300' => $isRecord,
                                                      'bg-slate-100 dark:bg-white/10' => ! $isRecord])>
                                            @if ($isRecord) <x-icon name="trophy" class="size-3.5 text-accent-500" /> @endif
                                            @if ($type->usesReps())
                                                {{ $set->reps ?? 0 }} ×@if (filled($set->weight) && (float) $set->weight !== 0.0) {{ $type->usesExtraWeight() && $set->weight > 0 ? '+' : '' }}{{ $n($set->weight) }} kg @endif
                                            @else
                                                {{ $set->duration_seconds ?? 0 }} sec
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        @elseif ($log = $item->cardio)
                            <p class="text-sm text-slate-600 dark:text-slate-300">
                                {{ collect([
                                    $log->duration_seconds ? \App\Models\WorkoutSession::clock($log->duration_seconds).' min' : null,
                                    $log->distance_m ? $n($log->distance_m / 1000).' km' : null,
                                    $log->metric && filled($log->metric_value) ? $log->metric->format($log->metric_value) : null,
                                    $log->pace_seconds ? 'pace '.\App\Models\CardioLog::pace($log->pace_seconds) : null,
                                    filled($log->stroke_rate) ? $log->stroke_rate.' spm' : null,
                                    filled($log->rotations) ? $n($log->rotations).' rotaties' : null,
                                    filled($log->floors) ? $log->floors.' floors' : null,
                                    ...collect(\App\Models\CardioLog::FIELDS)->map(fn ($f, $key) => filled($log->{$key}) ? $n($log->{$key}).($f[1] ? ' '.$f[1] : '').' '.mb_strtolower(str_replace('Gem. ', 'gem. ', $f[0])) : null)->all(),
                                ])->filter()->implode(' · ') ?: 'Geen gegevens ingevuld' }}
                            </p>
                            @if ($new = isset($records[$item->exercise_id]) ? $records[$item->exercise_id]->cardioRecords($log) : [])
                                <p class="mt-1 flex items-center gap-1.5 text-sm font-semibold text-accent-500">
                                    <x-icon name="trophy" class="size-4" /> Nieuw record: {{ mb_strtolower(implode(', ', $new)) }}
                                </p>
                            @endif
                            @if ($kmTimes = array_filter($log->kmTimes()))
                                @php($fastest = min($kmTimes))
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach ($kmTimes as $km => $seconds)
                                        <span @class(['rounded-lg px-2 py-0.5 text-sm tabular-nums',
                                                      'bg-emerald-100 font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $seconds === $fastest,
                                                      'bg-slate-100 dark:bg-white/10' => $seconds !== $fastest])
                                              title="{{ $seconds === $fastest ? 'Snelste km' : '' }}">
                                            km {{ $km }} · {{ \App\Models\CardioLog::pace($seconds) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </x-card>

    <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        @if ($session->workout)
            <x-button variant="secondary" :href="route('workouts.show', $session->workout)" wire:navigate>Naar de workout</x-button>
        @endif
        <x-button :href="route('dashboard')" wire:navigate icon="check">Klaar</x-button>
    </div>
</div>
