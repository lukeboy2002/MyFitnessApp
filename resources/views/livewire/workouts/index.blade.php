<div>
    <x-page-header title="Workouts" description="Je trainingsschema's: oefeningen met doelen, klaar om te starten.">
        <x-button icon="plus" :href="route('workouts.create')" wire:navigate>Workout maken</x-button>
    </x-page-header>

    @if ($message)
        <x-alert type="success" class="mb-5" wire:key="msg-{{ md5($message) }}">{{ $message }}</x-alert>
    @endif

    {{-- Tabs + zoeken --}}
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="inline-flex self-start rounded-full bg-white p-1 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-line-dark">
            @foreach ([false => ['Actief', $this->counts['active']], true => ['Gearchiveerd', $this->counts['archived']]] as $archived => [$label, $count])
                <button type="button" wire:click="$set('showArchived', {{ $archived ? 'true' : 'false' }})"
                        @class(['rounded-full px-4 py-2 text-sm font-semibold transition',
                                'bg-brand-700 text-white dark:bg-brand-500' => $showArchived == $archived,
                                'text-slate-600 hover:text-ink dark:text-slate-300 dark:hover:text-white' => $showArchived != $archived])>
                    {{ $label }} <span class="ml-0.5 opacity-70">{{ $count }}</span>
                </button>
            @endforeach
        </div>
        @if ($this->counts['active'] + $this->counts['archived'] > 5)
            <x-form.input wire:model.live.debounce.300ms="search" icon="search" placeholder="Zoek een workout…" aria-label="Zoeken" class="sm:ml-auto sm:w-72" />
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->workouts as $workout)
            <article wire:key="w-{{ $workout->id }}" style="--c: {{ $workout->color ?? '#1d63c5' }}"
                     class="group relative flex flex-col rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300 dark:bg-panel-dark dark:ring-line-dark dark:hover:ring-brand-500/50">
                <div class="h-1.5 rounded-t-2xl bg-(--c)"></div>
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="flex items-start gap-3">
                        <div class="grid size-11 shrink-0 place-items-center rounded-xl bg-[color-mix(in_oklab,var(--c)_14%,transparent)] text-(--c)">
                            <x-icon name="dumbbell" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-display text-xl leading-tight font-bold uppercase">
                                <a href="{{ route('workouts.show', $workout) }}" wire:navigate class="after:absolute after:inset-0 focus:outline-none">{{ $workout->name }}</a>
                            </h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ $workout->exercises_count }} {{ $workout->exercises_count === 1 ? 'oefening' : 'oefeningen' }}@if ($minutes = $workout->minutes()) · ± {{ $minutes }} min @endif
                            </p>
                        </div>

                        {{-- Acties boven de klikbare kaart --}}
                        <div x-data="{ open: false }" class="relative z-10" x-on:click.outside="open = false">
                            <button type="button" x-on:click="open = ! open" aria-label="Acties"
                                    class="grid size-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-ink dark:hover:bg-white/10 dark:hover:text-white">
                                <x-icon name="dots" />
                            </button>
                            <div x-show="open" x-cloak x-transition.origin.top.right
                                 class="absolute right-0 top-10 w-44 overflow-hidden rounded-xl bg-white py-1 text-sm shadow-lg ring-1 ring-slate-200 dark:bg-panel-dark dark:ring-line-dark">
                                <a href="{{ route('workouts.edit', $workout) }}" wire:navigate class="block px-4 py-2 hover:bg-slate-50 dark:hover:bg-white/5">Bewerken</a>
                                <button type="button" wire:click="duplicate({{ $workout->id }})" class="block w-full px-4 py-2 text-left hover:bg-slate-50 dark:hover:bg-white/5">Dupliceren</button>
                                @if ($workout->isArchived())
                                    <button type="button" wire:click="restore({{ $workout->id }})" class="block w-full px-4 py-2 text-left hover:bg-slate-50 dark:hover:bg-white/5">Terugzetten</button>
                                @else
                                    <button type="button" wire:click="archive({{ $workout->id }})" class="block w-full px-4 py-2 text-left hover:bg-slate-50 dark:hover:bg-white/5">Archiveren</button>
                                @endif
                                <button type="button" wire:click="confirmDelete({{ $workout->id }})" x-on:click="open = false"
                                        class="block w-full px-4 py-2 text-left text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Verwijderen</button>
                            </div>
                        </div>
                    </div>

                    @if ($workout->muscleGroups->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($workout->muscleGroups as $group)
                                <x-badge :hex="$group->color">{{ $group->name }}</x-badge>
                            @endforeach
                        </div>
                    @endif

                    @if ($workout->exercises->isNotEmpty())
                        <p class="line-clamp-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ $workout->exercises->pluck('exercise.name')->implode(' · ') }}
                        </p>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center ring-1 ring-slate-200 sm:col-span-2 xl:col-span-3 dark:bg-panel-dark dark:ring-line-dark">
                @if ($search !== '')
                    <p class="text-slate-500 dark:text-slate-400">Geen workouts gevonden voor "{{ $search }}".</p>
                @elseif ($showArchived)
                    <p class="text-slate-500 dark:text-slate-400">Er staan geen workouts in het archief.</p>
                @else
                    <div class="mx-auto mb-3 grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="dumbbell" class="size-7" />
                    </div>
                    <p class="font-semibold">Nog geen workouts</p>
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Stel een workout samen uit je oefeningen, met sets, herhalingen en gewicht per oefening.</p>
                    <x-button icon="plus" :href="route('workouts.create')" wire:navigate>Eerste workout maken</x-button>
                @endif
            </div>
        @endforelse
    </div>

    <x-confirm-delete :name="$deletingName">
        De oefeningen zelf blijven bestaan. Liever bewaren? Archiveer de workout dan.
    </x-confirm-delete>
</div>
