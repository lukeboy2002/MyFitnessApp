@php
    use App\Models\WorkoutSession;
    use App\Support\TrainingStats;

    $n = fn ($v) => WorkoutSession::formatNumber($v);
    $goal = $user->weekly_goal_minutes ? $user->weekly_goal_minutes * 60 : null;
    $percent = $goal ? min(100, round($thisWeek['seconds'] / $goal * 100)) : null;
@endphp

<div>
    <x-page-header title="Hoi {{ str($user->name)->before(' ') }}" :description="now()->translatedFormat('l j F').' · week '.now()->isoWeek()">
        <x-button variant="fire" icon="play" :href="route('quickstart')" wire:navigate>Quickstart</x-button>
    </x-page-header>

    {{-- Lopende training --}}
    @if ($running)
        <a href="{{ route('sessions.run', $running) }}" wire:navigate style="--c: {{ $running->color ?? '#1d63c5' }}"
           x-data="{ s: {{ $running->elapsedSeconds() }}, paused: {{ $running->isPaused() ? 'true' : 'false' }} }"
           x-init="setInterval(() => paused || s++, 1000)"
           class="mb-5 flex items-center gap-3 rounded-2xl bg-(--c) px-4 py-3 text-white shadow-md transition hover:brightness-110">
            <span class="relative grid size-10 shrink-0 place-items-center rounded-xl bg-white/20">
                <x-icon :name="$running->isPaused() ? 'pause' : 'play'" class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-bold uppercase tracking-wide text-white/80">{{ $running->isPaused() ? 'Training gepauzeerd' : 'Training bezig' }}</span>
                <span class="block truncate font-semibold">{{ $running->name }}</span>
            </span>
            <span class="font-display text-2xl font-bold tabular-nums"
                  x-text="(s >= 3600 ? Math.floor(s / 3600) + ':' : '') + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0')">{{ WorkoutSession::clock($running->elapsedSeconds()) }}</span>
            <span class="hidden font-semibold sm:inline">Verder</span>
            <x-icon name="chevron-right" class="size-5" />
        </a>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        {{-- Weektotaal --}}
        <x-card class="border-0 bg-linear-to-br from-brand-600 via-brand-700 to-brand-900 text-white ring-0 col-span-2 dark:bg-linear-to-br">
            <p class="text-xs font-bold uppercase tracking-widest text-white/80">Trainingstijd deze week</p>
            <p class="mt-1 font-display text-5xl font-extrabold tabular-nums">
                {{ TrainingStats::hours($thisWeek['seconds']) }}
                @if ($goal)
                    <span class="text-2xl text-white/70">/ {{ TrainingStats::hours($goal) }}</span>
                @endif
            </p>

            @if ($goal)
                <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-white/20" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Weekdoel">
                    <div class="h-full rounded-full bg-linear-to-r from-accent-400 to-fire-500" style="width: {{ $percent }}%"></div>
                </div>
                <p class="mt-2 text-sm text-white/80">
                    @if ($thisWeek['seconds'] >= $goal)
                        <span class="font-semibold text-accent-300">Weekdoel gehaald!</span>
                    @else
                        Nog {{ TrainingStats::hours($goal - $thisWeek['seconds']) }} tot je weekdoel · {{ $percent }}%
                    @endif
                </p>
            @else
                <p class="mt-3 text-sm text-white/80">
                    <a href="{{ route('profile.edit') }}#weekdoel" wire:navigate class="font-semibold text-white underline">Stel een weekdoel in</a> om je voortgang te zien.
                </p>
            @endif
        </x-card>

        <x-card>
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Trainingen</p>
            <p class="mt-1 font-display text-4xl font-extrabold tabular-nums sm:text-5xl">
                {{ $thisWeek['count'] }}
                @if ($user->weekly_goal_sessions)
                    <span class="text-xl text-slate-400 sm:text-2xl">/ {{ $user->weekly_goal_sessions }}</span>
                @endif
            </p>
            @if ($user->weekly_goal_sessions)
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10" role="progressbar" aria-valuenow="{{ $thisWeek['count'] }}" aria-valuemin="0" aria-valuemax="{{ $user->weekly_goal_sessions }}" aria-label="Trainingen deze week">
                    <div class="h-full rounded-full bg-linear-to-r from-accent-400 to-fire-500" style="width: {{ min(100, round($thisWeek['count'] / $user->weekly_goal_sessions * 100)) }}%"></div>
                </div>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    @if ($thisWeek['count'] >= $user->weekly_goal_sessions)
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">Doel gehaald!</span>
                    @else
                        Nog {{ $user->weekly_goal_sessions - $thisWeek['count'] }} te gaan
                    @endif
                    · vorige week {{ $lastWeek['count'] }}
                </p>
            @else
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $thisWeek['sets'] }} sets · vorige week {{ $lastWeek['count'] }}</p>
            @endif
        </x-card>

        <x-card>
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Volume kracht</p>
            <p class="mt-1 font-display text-[28px] font-extrabold whitespace-nowrap tabular-nums sm:text-5xl lg:text-4xl 2xl:text-5xl">{{ TrainingStats::kilos($thisWeek['volume']) }}<span class="text-lg text-slate-400 sm:text-2xl"> kg</span></p>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                @if ($thisWeek['distance_m'])
                    {{ $n(round($thisWeek['distance_m'] / 1000, 1)) }} km cardio ·
                @endif
                vorige week {{ TrainingStats::kilos($lastWeek['volume']) }} kg
            </p>
        </x-card>

        {{-- Eerstvolgende geplande workout --}}
        <x-card class="col-span-2 lg:col-span-4" :padding="false">
            <div class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6" @if ($next) style="--c: {{ $next->workout->color ?? '#1d63c5' }}" @endif>
                <span @class(['grid size-11 shrink-0 place-items-center rounded-xl',
                              'bg-[color-mix(in_oklab,var(--c)_15%,transparent)] text-(--c)' => $next,
                              'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300' => ! $next])>
                    <x-icon name="calendar" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Volgende workout</p>
                    @if ($next)
                        <p class="truncate font-semibold">
                            {{ $next->workout->name }}
                            <span class="font-normal text-slate-500 dark:text-slate-400">· {{ $next->scheduled_on->isToday() ? 'vandaag' : ($next->scheduled_on->isTomorrow() ? 'morgen' : $next->scheduled_on->translatedFormat('l j F')) }}</span>
                        </p>
                    @else
                        <p class="text-slate-500 dark:text-slate-400">Niets gepland.</p>
                    @endif
                </div>
                @if ($next?->scheduled_on->isToday() && ! $running)
                    <x-button icon="play" :href="route('workouts.show', $next->workout)" wire:navigate>Bekijken en starten</x-button>
                @endif
                <x-button variant="secondary" icon="calendar" :href="route('calendar', $next ? ['day' => $next->scheduled_on->toDateString()] : [])" wire:navigate>Kalender</x-button>
            </div>
        </x-card>

        {{-- Doel uit het profiel: de laatste 30 dagen en een tip --}}
        <x-card class="col-span-2">
            <div class="flex items-center gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-accent-400/15 text-fire-500">
                    <x-icon name="trophy" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Jouw doel</p>
                    <p class="truncate font-semibold">{{ $goalProgress?->goal->label() ?? 'Nog geen doel gekozen' }}</p>
                </div>
                @if ($goalProgress)
                    <a href="{{ $goalProgress->link['url'] }}" wire:navigate class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">{{ $goalProgress->link['label'] }}</a>
                @endif
            </div>

            @if ($goalProgress)
                @if ($goalProgress->value !== null)
                    <p class="mt-4 font-display text-4xl font-extrabold tabular-nums">{{ $goalProgress->value }}<span class="text-lg text-slate-400 sm:text-2xl"> {{ $goalProgress->unit }}</span></p>
                    @if ($goalProgress->goal !== \App\Enums\FitnessGoal::LoseWeight)
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">in de laatste {{ \App\Support\GoalProgress::DAYS }} dagen</p>
                    @endif
                @endif
                @if ($goalProgress->detail)
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $goalProgress->detail }}</p>
                @endif
                @if ($goalProgress->percent !== null)
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10" role="progressbar" aria-valuenow="{{ $goalProgress->percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Streefgewicht">
                        <div class="h-full rounded-full bg-linear-to-r from-accent-400 to-fire-500" style="width: {{ $goalProgress->percent }}%"></div>
                    </div>
                @endif
                <p class="mt-4 flex gap-2 rounded-xl bg-slate-50 px-3 py-2.5 text-sm text-slate-600 dark:bg-white/5 dark:text-slate-300">
                    <x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-600 dark:text-brand-300" />
                    <span>{{ $goalProgress->tip }}</span>
                </p>
            @else
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    Kies wat je wilt bereiken, dan houdt het dashboard je voortgang bij.
                    <a href="{{ route('profile.edit') }}#persoonlijk" wire:navigate class="font-semibold text-brand-700 hover:underline dark:text-brand-300">Doel kiezen</a>
                </p>
            @endif
        </x-card>

        {{-- Laatste 12 weken --}}
        <x-card title="Laatste 12 weken" description="Trainingstijd per week" class="col-span-2">
            <x-chart.bars class="mt-2"
                :goal="$user->weekly_goal_minutes"
                :goal-label="$goal ? 'Doel '.TrainingStats::hours($goal) : null"
                :bars="$weeks->map(fn ($week) => [
                    'label' => 'Week '.$week['start']->isoWeek().' · '.$week['start']->translatedFormat('j M'),
                    'short' => $week['start']->isoWeek(),
                    'value' => round($week['seconds'] / 60),
                    'tip' => TrainingStats::hours($week['seconds']).' · '.$week['count'].' '.($week['count'] === 1 ? 'training' : 'trainingen'),
                    'current' => $week['start']->isCurrentWeek(),
                ])->all()" />
        </x-card>

        {{-- Laatste trainingen --}}
        <x-card title="Laatste trainingen" :padding="false" class="col-span-2">
            <x-slot:actions>
                <a href="{{ route('progress.index') }}" wire:navigate class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Progress</a>
            </x-slot:actions>

            @if ($recent->isEmpty())
                <p class="px-5 py-5 text-slate-500 sm:px-6 dark:text-slate-400">Nog geen trainingen afgerond. Start er een met Quickstart of vanuit een workout.</p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 dark:divide-line-dark">
                    @foreach ($recent as $session)
                        <li style="--c: {{ $session->color ?? '#1d63c5' }}">
                            <a href="{{ route('sessions.summary', $session) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 sm:px-6 dark:hover:bg-white/[.03]">
                                <span class="h-9 w-1.5 shrink-0 rounded-full bg-(--c)"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold">{{ $session->name }}</span>
                                    <span class="block text-sm text-slate-500 dark:text-slate-400">{{ $session->started_at->translatedFormat('D j M, H:i') }} · {{ $session->exercises->count() }} oefeningen</span>
                                </span>
                                <span class="font-semibold tabular-nums">{{ WorkoutSession::clock($session->duration_seconds ?? 0) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Badges: de laatst behaalde --}}
        <x-card title="Badges" :padding="false" class="col-span-2">
            <x-slot:actions>
                <a href="{{ route('badges') }}" wire:navigate class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Alle badges</a>
            </x-slot:actions>

            @if ($badges->isEmpty())
                <p class="px-5 py-5 text-slate-500 sm:px-6 dark:text-slate-400">Rond je eerste training af voor je eerste badge.</p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 dark:divide-line-dark">
                    @foreach ($badges as $row)
                        <li class="flex items-center gap-3 px-5 py-3 sm:px-6">
                            <x-medal :badge="$row['badge']" :level="$row['level']" size="sm" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $row['badge']->labelFor($row['level']) }}</span>
                                <span class="block text-sm text-slate-500 dark:text-slate-400">{{ $row['badge']->name }} · {{ ucfirst($row['badge']->tier($row['level'])) }}</span>
                            </span>
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $row['at']->translatedFormat('j M') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Eigen oefeningen (alleen als je die hebt) --}}
        @if ($ownExercises->isNotEmpty())
            <x-card title="Eigen oefeningen" :padding="false" class="col-span-2">
                <x-slot:actions>
                    <a href="{{ route('exercises.index', ['eigen' => 1]) }}" wire:navigate class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
                        {{ $ownExercisesCount > $ownExercises->count() ? 'Alle '.$ownExercisesCount : 'Bekijken' }}
                    </a>
                </x-slot:actions>

                <ul class="mt-3 divide-y divide-slate-100 dark:divide-line-dark">
                    @foreach ($ownExercises as $exercise)
                        @php($primary = $exercise->primaryMuscleGroup())
                        <li style="--g: {{ $primary?->color ?? '#1d63c5' }}">
                            <a href="{{ route('exercises.show', $exercise) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 sm:px-6 dark:hover:bg-white/[.03]">
                                <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-50 dark:bg-white/5">
                                    @if ($picture = $exercise->imageUrl())
                                        <img src="{{ $picture }}" alt="" loading="lazy" class="size-full object-contain p-0.5">
                                    @else
                                        <x-icon :name="$exercise->type->icon()" class="size-5 text-(--g)" />
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold">{{ $exercise->name }}</span>
                                    <span class="block text-sm text-slate-500 dark:text-slate-400">{{ $exercise->type->label() }}@if ($primary) · {{ $primary->name }}@endif</span>
                                </span>
                                <x-icon name="chevron-right" class="size-5 text-slate-400" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</div>
