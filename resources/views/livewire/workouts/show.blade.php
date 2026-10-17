<div style="--c: {{ $workout->color ?? '#1d63c5' }}">
    <a href="{{ route('workouts.index', $workout->isArchived() ? ['archief' => 1] : []) }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
        <x-icon name="chevron-right" class="size-4 rotate-180" /> Alle workouts
    </a>

    <x-page-header :title="$workout->name" :description="$workout->description">
        <x-button variant="secondary" type="button" icon="archive" wire:click="archive" loading="archive">
            {{ $workout->isArchived() ? 'Terugzetten' : 'Archiveren' }}
        </x-button>
        <x-button variant="secondary" :href="route('workouts.edit', $workout)" wire:navigate>Bewerken</x-button>
        @if ($running)
            <x-button variant="fire" icon="play" :href="route('sessions.run', $running)" wire:navigate>Verder met training</x-button>
        @else
            <x-button variant="fire" icon="play" type="button" wire:click="start" loading="start" :disabled="$workout->isArchived() || $workout->exercises->isEmpty()">Start workout</x-button>
        @endif
    </x-page-header>

    @if ($running && $running->workout_id !== $workout->id)
        <x-alert type="info" :timeout="0" class="mb-5">Je hebt nog een training lopen: <a href="{{ route('sessions.run', $running) }}" wire:navigate class="font-semibold underline">{{ $running->name }}</a>. Rond die eerst af.</x-alert>
    @endif
    @if ($workout->isArchived())
        <x-alert type="warning" class="mb-5">Deze workout is gearchiveerd. Zet hem terug om hem weer te kunnen starten.</x-alert>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_18rem]">
        <x-card :padding="false" class="overflow-hidden">
            <ol class="divide-y divide-slate-100 dark:divide-line-dark">
                @foreach ($workout->exercises as $item)
                    @php($exercise = $item->exercise)
                    @php($primary = $exercise->primaryMuscleGroup())
                    <li wire:key="i-{{ $item->id }}" class="flex items-center gap-4 px-4 py-3 sm:px-5" style="--g: {{ $primary?->color ?? '#1d63c5' }}">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-(--c) text-sm font-bold text-white">{{ $loop->iteration }}</span>
                        <div class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-[radial-gradient(circle_at_50%_30%,white,color-mix(in_oklab,var(--g)_22%,var(--color-canvas))_80%)] dark:bg-[radial-gradient(circle_at_50%_30%,#1b2a45,color-mix(in_oklab,var(--g)_30%,var(--color-canvas-dark))_85%)]">
                            @if ($picture = $exercise->imageUrl())
                                <img src="{{ $picture }}" alt="" loading="lazy" class="size-full object-contain p-1">
                            @else
                                <x-icon :name="$exercise->type->icon()" class="size-6 text-(--g)" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('exercises.show', $exercise) }}" wire:navigate class="font-semibold hover:underline">{{ $exercise->name }}</a>
                            <p class="text-sm text-slate-600 dark:text-slate-300">
                                <span class="font-semibold text-ink dark:text-white">{{ $item->targetSummary() ?? 'Geen doel' }}</span>
                                @if ($item->rest_seconds && $exercise->type->usesSets()) <span class="text-slate-400">· rust {{ \App\Models\WorkoutExercise::duration($item->rest_seconds) }}</span> @endif
                            </p>
                            @if ($item->notes)
                                <p class="mt-0.5 text-sm text-slate-500 italic dark:text-slate-400">{{ $item->notes }}</p>
                            @endif
                        </div>
                        <x-badge :color="$exercise->type->color()" :icon="$exercise->type->icon()" class="hidden sm:inline-flex">{{ $exercise->type->label() }}</x-badge>
                    </li>
                @endforeach
            </ol>
        </x-card>

        <x-card>
            <dl class="grid gap-4 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5">
                        <dt class="text-slate-500 dark:text-slate-400">Oefeningen</dt>
                        <dd class="font-display text-2xl font-bold">{{ $workout->exercises->count() }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5">
                        <dt class="text-slate-500 dark:text-slate-400">Duur</dt>
                        <dd class="font-display text-2xl font-bold">± {{ $workout->minutes() ?? '–' }} <span class="text-base">min</span></dd>
                    </div>
                </div>
                @if ($workout->muscleGroups->isNotEmpty())
                    <div>
                        <dt class="mb-1.5 font-semibold text-slate-500 dark:text-slate-400">Spiergroepen</dt>
                        <dd class="flex flex-wrap gap-1.5">
                            @foreach ($workout->muscleGroups as $group)
                                <x-badge :hex="$group->color">{{ $group->name }}</x-badge>
                            @endforeach
                        </dd>
                    </div>
                @endif
                @php($sets = $workout->exercises->filter(fn ($i) => $i->exercise->type->usesSets())->sum('target_sets'))
                @if ($sets)
                    <div>
                        <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">Totaal sets</dt>
                        <dd class="font-semibold">{{ $sets }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>
    </div>
</div>
