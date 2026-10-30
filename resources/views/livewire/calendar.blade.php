@php
    use App\Livewire\Calendar;
    use App\Models\WorkoutSession;

    $month = $this->monthStart();
    $daySessions = $sessions->get($selected->toDateString(), collect());
    $dayPlanned = $planned->get($selected->toDateString(), collect())->reject->isDone();
@endphp

<div>
    <x-page-header title="Kalender" description="Je trainingen per dag. Plan vooruit welke workout je wanneer doet." />

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
        {{-- Maand --}}
        <x-card :padding="false" class="overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-4 sm:px-5">
                <h2 class="min-w-0 flex-1 font-display text-2xl font-bold uppercase">{{ ucfirst($month->translatedFormat('F Y')) }}</h2>
                @unless ($month->isSameMonth(today()) && $selected->isToday())
                    <x-button variant="secondary" size="sm" type="button" wire:click="goToday">Vandaag</x-button>
                @endunless
                <button type="button" wire:click="previousMonth" aria-label="Vorige maand"
                        class="grid size-9 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-ink dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
                    <x-icon name="chevron-right" class="size-5 rotate-180" />
                </button>
                <button type="button" wire:click="nextMonth" aria-label="Volgende maand"
                        class="grid size-9 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-ink dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
                    <x-icon name="chevron-right" class="size-5" />
                </button>
            </div>

            <div class="grid grid-cols-7 border-y border-slate-100 bg-slate-50 dark:border-line-dark dark:bg-white/[.03]">
                @foreach (['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'] as $weekday)
                    <div class="py-2 text-center text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $weekday }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7">
                @foreach ($days as $date)
                    @php
                        $key = $date->toDateString();
                        $done = $sessions->get($key, collect());
                        $open = $planned->get($key, collect())->reject->isDone();
                        $isSelected = $date->isSameDay($selected);
                    @endphp
                    <button type="button" wire:key="d-{{ $key }}" wire:click="select('{{ $key }}')"
                            aria-label="{{ $date->translatedFormat('l j F') }}: {{ $done->count() }} trainingen, {{ $open->count() }} gepland"
                            @class(['relative flex min-h-16 flex-col items-center gap-1 border-b border-r border-slate-100 p-1 text-left transition sm:min-h-24 sm:items-stretch sm:p-1.5 dark:border-line-dark',
                                    '[&:nth-child(7n)]:border-r-0',
                                    'bg-brand-50 dark:bg-brand-500/10' => $isSelected,
                                    'hover:bg-slate-50 dark:hover:bg-white/[.03]' => ! $isSelected])>
                        <span @class(['grid size-7 shrink-0 place-items-center rounded-full text-sm font-semibold tabular-nums sm:self-end',
                                      'bg-brand-700 text-white dark:bg-brand-500' => $date->isToday(),
                                      'text-slate-300 dark:text-slate-600' => ! $date->isToday() && ! $date->isSameMonth($month),
                                      'ring-2 ring-brand-500' => $isSelected && ! $date->isToday()])>
                            {{ $date->day }}
                        </span>

                        {{-- Mobiel: stipjes --}}
                        <span class="flex flex-wrap justify-center gap-1 sm:hidden">
                            @foreach ($done->take(3) as $session)
                                <span class="size-2 rounded-full" style="background-color: {{ $session->color ?? '#1d63c5' }}"></span>
                            @endforeach
                            @foreach ($open->take(3 - min(3, $done->count())) as $item)
                                <span class="size-2 rounded-full border-2 bg-transparent" style="border-color: {{ $item->isMissed() ? '#94a3b8' : $item->workout->color ?? '#1d63c5' }}"></span>
                            @endforeach
                        </span>

                        {{-- Groter scherm: namen --}}
                        <span class="hidden min-w-0 flex-col gap-1 sm:flex">
                            @foreach ($done->take(2) as $session)
                                <span class="truncate rounded-md px-1.5 py-0.5 text-[11px] font-semibold text-white" style="background-color: {{ $session->color ?? '#1d63c5' }}">{{ $session->name }}</span>
                            @endforeach
                            @foreach ($open->take(max(0, 2 - $done->count())) as $item)
                                <span @class(['truncate rounded-md border border-dashed px-1.5 py-0.5 text-[11px] font-semibold', 'text-slate-400 line-through' => $item->isMissed()])
                                      style="border-color: {{ $item->isMissed() ? '#94a3b8' : $item->workout->color ?? '#1d63c5' }}; {{ $item->isMissed() ? '' : 'color: '.($item->workout->color ?? '#1d63c5') }}">{{ $item->workout->name }}</span>
                            @endforeach
                            @if ($done->count() + $open->count() > 2)
                                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">+{{ $done->count() + $open->count() - 2 }}</span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-4 px-4 py-3 text-xs font-semibold text-slate-500 sm:px-5 dark:text-slate-400">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-brand-600"></span> Gedaan</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full border-2 border-brand-600"></span> Gepland</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full border-2 border-slate-400"></span> Gemist</span>
            </div>
        </x-card>

        {{-- Gekozen dag --}}
        <x-card :title="ucfirst($selected->translatedFormat('l j F'))" :description="match (true) { $selected->isToday() => 'Vandaag', $selected->isTomorrow() => 'Morgen', $selected->isFuture() => 'Over '.(int) today()->diffInDays($selected).' dagen', default => null }">
            @if ($daySessions->isEmpty() && $dayPlanned->isEmpty())
                <p class="text-slate-500 dark:text-slate-400">{{ $selected->isBefore(today()) ? 'Geen training op deze dag.' : 'Nog niets gepland.' }}</p>
            @endif

            <ul class="grid gap-2.5">
                @foreach ($daySessions as $session)
                    <li wire:key="s-{{ $session->id }}" style="--c: {{ $session->color ?? '#1d63c5' }}">
                        <a href="{{ $session->isRunning() ? route('sessions.run', $session) : route('sessions.summary', $session) }}" wire:navigate
                           class="flex items-center gap-3 rounded-xl p-2.5 ring-1 ring-slate-200 transition hover:bg-slate-50 dark:ring-line-dark dark:hover:bg-white/[.03]">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-(--c) text-white">
                                <x-icon :name="$session->isRunning() ? 'play' : 'check'" class="size-4.5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $session->name }}</span>
                                <span class="block text-sm text-slate-500 dark:text-slate-400">
                                    {{ $session->started_at->format('H:i') }} ·
                                    {{ $session->isRunning() ? 'bezig' : WorkoutSession::clock($session->duration_seconds ?? 0).' · '.$session->exercises_count.' oefeningen' }}
                                </span>
                                @if ($session->notes)
                                    <span class="block truncate text-sm italic text-slate-500 dark:text-slate-400" title="{{ $session->notes }}">“{{ $session->notes }}”</span>
                                @endif
                            </span>
                            <x-icon name="chevron-right" class="size-5 text-slate-400" />
                        </a>
                    </li>
                @endforeach

                @foreach ($dayPlanned as $item)
                    <li wire:key="p-{{ $item->id }}" style="--c: {{ $item->isMissed() ? '#94a3b8' : $item->workout->color ?? '#1d63c5' }}"
                        class="flex items-center gap-3 rounded-xl border-2 border-dashed border-(--c) p-2.5">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-[color-mix(in_oklab,var(--c)_15%,transparent)] text-(--c)">
                            <x-icon name="calendar" class="size-4.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <a href="{{ route('workouts.show', $item->workout) }}" wire:navigate class="block truncate font-semibold hover:underline">{{ $item->workout->name }}</a>
                            <span class="block text-sm text-slate-500 dark:text-slate-400">{{ $item->isMissed() ? 'Gemist' : 'Gepland' }}{{ $item->notes ? ' · '.$item->notes : '' }}</span>
                        </span>
                        @if ($item->scheduled_on->isToday() && ! $item->workout->isArchived())
                            <x-button size="sm" icon="play" type="button" wire:click="start({{ $item->id }})" loading="start({{ $item->id }})">Start</x-button>
                        @endif
                        <button type="button" wire:click="confirmDelete({{ $item->id }})" aria-label="Planning verwijderen"
                                class="grid size-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400">
                            <x-icon name="x" class="size-4.5" />
                        </button>
                    </li>
                @endforeach
            </ul>

            @unless ($selected->isBefore(today()))
                <x-slot:footer>
                    <x-button variant="secondary" icon="plus" type="button" wire:click="openPlanning" :block="true">Workout plannen</x-button>
                </x-slot:footer>
            @endunless
        </x-card>
    </div>

    {{-- Plannen --}}
    <x-modal wire:model="planning" title="Workout plannen" maxWidth="md">
        @if ($this->workouts->isEmpty())
            <p class="text-slate-500 dark:text-slate-400">Je hebt nog geen actieve workouts. <a href="{{ route('workouts.create') }}" wire:navigate class="font-semibold text-brand-700 underline dark:text-brand-300">Maak er eerst een</a>.</p>
        @else
            <form wire:submit="plan" id="plan-form" class="grid gap-4">
                <p class="text-sm text-slate-500 dark:text-slate-400">Op {{ $selected->translatedFormat('l j F') }}</p>
                <x-form.select label="Workout" wire:model="workout_id" :options="$this->workouts->pluck('name', 'id')" required />
                <x-form.select label="Elke week herhalen" wire:model="repeat" :options="Calendar::REPEAT" hint="Op dezelfde dag van de week" />
                <x-form.input label="Notitie" wire:model="notes" placeholder="Optioneel" />
            </form>
        @endif

        <x-slot:footer>
            <x-button variant="ghost" type="button" x-on:click="show = false">Annuleren</x-button>
            @if ($this->workouts->isNotEmpty())
                <x-button form="plan-form" icon="calendar" loading="plan">Plannen</x-button>
            @endif
        </x-slot:footer>
    </x-modal>

    <x-confirm-delete :name="$deletingName" title="Planning verwijderen?">Je trainingen blijven gewoon staan.</x-confirm-delete>
</div>
