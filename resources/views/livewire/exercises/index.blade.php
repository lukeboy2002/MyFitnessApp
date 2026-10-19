<div>
    <x-page-header title="Exercises" description="Alle oefeningen: de standaardlijst van je gym en je eigen oefeningen.">
        <x-button icon="plus" :href="route('exercises.create')" wire:navigate>Oefening toevoegen</x-button>
    </x-page-header>

    @if ($message)
        <x-alert type="success" class="mb-5" wire:key="msg-{{ md5($message) }}">{{ $message }}</x-alert>
    @endif
    @if ($error)
        <x-alert type="danger" class="mb-5" wire:key="err-{{ md5($error) }}">{{ $error }}</x-alert>
    @endif

    {{-- Filters --}}
    <div class="mb-5 flex flex-col gap-3">
        <div class="grid gap-3 sm:grid-cols-[1fr_16rem]">
            <x-form.input wire:model.live.debounce.300ms="search" icon="search" placeholder="Zoek een oefening…" aria-label="Zoeken" />
            <x-form.select wire:model.live="groupFilter" :options="$this->muscleGroups->pluck('name', 'id')" placeholder="Alle spiergroepen" aria-label="Spiergroep" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @foreach (['' => 'Alles'] + collect(\App\Enums\ExerciseType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all() as $value => $label)
                <button type="button" wire:click="$set('typeFilter', '{{ $value }}')" wire:key="t-{{ $value ?: 'all' }}"
                        @class(['rounded-full px-4 py-2 text-sm font-semibold ring-1 ring-inset transition',
                                'bg-brand-700 text-white ring-brand-700 dark:bg-brand-500 dark:ring-brand-500' => $typeFilter === $value,
                                'bg-white ring-slate-300 hover:bg-slate-50 dark:bg-white/5 dark:ring-line-dark' => $typeFilter !== $value])>{{ $label }}</button>
            @endforeach

            @if ($this->ownCount)
                <button type="button" wire:click="$toggle('ownOnly')" aria-pressed="{{ $ownOnly ? 'true' : 'false' }}"
                        @class(['ml-auto inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold ring-1 ring-inset transition',
                                'bg-brand-700 text-white ring-brand-700 dark:bg-brand-500 dark:ring-brand-500' => $ownOnly,
                                'bg-white ring-slate-300 hover:bg-slate-50 dark:bg-white/5 dark:ring-line-dark' => ! $ownOnly])>
                    <x-icon name="user" class="size-4" /> Eigen oefeningen
                </button>
            @endif
            <button type="button" wire:click="$toggle('showArchived')"
                    @class(['inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold ring-1 ring-inset transition',
                            'bg-slate-700 text-white ring-slate-700 dark:bg-white/20 dark:ring-white/20' => $showArchived,
                            'ml-auto' => ! $this->ownCount,
                            'bg-white ring-slate-300 hover:bg-slate-50 dark:bg-white/5 dark:ring-line-dark' => ! $showArchived])>
                <x-icon name="archive" class="size-4" /> Archief @if ($this->archivedCount) ({{ $this->archivedCount }}) @endif
            </button>
        </div>
    </div>

    <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
        {{ $this->exercises->count() }} {{ $this->exercises->count() === 1 ? 'oefening' : 'oefeningen' }}{{ $showArchived ? ' in het archief' : '' }}
    </p>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->exercises as $exercise)
            @php($primary = $exercise->primaryMuscleGroup())
            <article wire:key="ex-{{ $exercise->id }}" style="--c: {{ $primary?->color ?? '#1d63c5' }}"
                     class="group relative flex gap-4 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300 dark:bg-panel-dark dark:ring-line-dark dark:hover:ring-brand-500/50">
                <div @class(['grid size-24 shrink-0 place-items-center overflow-hidden rounded-xl',
                             'bg-white ring-1 ring-slate-100 dark:ring-0' => $exercise->image_path,
                             'bg-[radial-gradient(circle_at_50%_30%,white,color-mix(in_oklab,var(--c)_22%,var(--color-canvas))_80%)] dark:bg-[radial-gradient(circle_at_50%_30%,#1b2a45,color-mix(in_oklab,var(--c)_30%,var(--color-canvas-dark))_85%)]' => ! $exercise->image_path])>
                    @if ($picture = $exercise->imageUrl())
                        <img src="{{ $picture }}" alt="" loading="lazy" @class(['size-full object-contain', 'p-1.5' => ! $exercise->image_path])>
                    @else
                        <x-icon :name="$exercise->type->icon()" class="size-9 text-(--c)" />
                    @endif
                </div>

                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                    <h3 class="font-display text-lg leading-tight font-bold uppercase">
                        <a href="{{ route('exercises.show', $exercise) }}" wire:navigate class="after:absolute after:inset-0 focus:outline-none">{{ $exercise->name }}</a>
                    </h3>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <x-badge :color="$exercise->type->color()" :icon="$exercise->type->icon()">{{ $exercise->type->label() }}</x-badge>
                        @if ($primary && $primary->name !== $exercise->type->label())
                            <x-badge :hex="$primary->color">{{ $primary->name }}</x-badge>
                        @endif
                        @unless ($exercise->isStandard())
                            <x-badge color="brand">Eigen</x-badge>
                        @endunless
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $exercise->equipment?->label() }}@if ($target = $exercise->defaultTarget()) · {{ $target }}@endif
                    </p>
                </div>

                {{-- Acties boven de klikbare kaart --}}
                <div x-data="{ open: false }" class="relative z-10 self-start" x-on:click.outside="open = false">
                    <button type="button" x-on:click="open = ! open" aria-label="Acties"
                            class="grid size-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-ink dark:hover:bg-white/10 dark:hover:text-white">
                        <x-icon name="dots" />
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right
                         class="absolute right-0 top-10 w-48 overflow-hidden rounded-xl bg-white py-1 text-sm shadow-lg ring-1 ring-slate-200 dark:bg-panel-dark dark:ring-line-dark">
                        @can('update', $exercise)
                            <a href="{{ route('exercises.edit', $exercise) }}" wire:navigate class="block px-4 py-2 hover:bg-slate-50 dark:hover:bg-white/5">Bewerken</a>
                        @endcan
                        <button type="button" wire:click="duplicate({{ $exercise->id }})" class="block w-full px-4 py-2 text-left hover:bg-slate-50 dark:hover:bg-white/5">
                            {{ $exercise->isStandard() ? 'Kopiëren naar eigen' : 'Dupliceren' }}
                        </button>
                        @can('update', $exercise)
                            @if ($exercise->isArchived())
                                <button type="button" wire:click="restore({{ $exercise->id }})" class="block w-full px-4 py-2 text-left hover:bg-slate-50 dark:hover:bg-white/5">Terugzetten</button>
                            @else
                                <button type="button" wire:click="archive({{ $exercise->id }})" class="block w-full px-4 py-2 text-left hover:bg-slate-50 dark:hover:bg-white/5">Archiveren</button>
                            @endif
                            <button type="button" wire:click="confirmDelete({{ $exercise->id }})" x-on:click="open = false"
                                    class="block w-full px-4 py-2 text-left text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">Verwijderen</button>
                        @endcan
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center ring-1 ring-slate-200 sm:col-span-2 xl:col-span-3 dark:bg-panel-dark dark:ring-line-dark">
                <p class="text-slate-500 dark:text-slate-400">{{ $showArchived ? 'Er staan geen oefeningen in het archief.' : 'Geen oefeningen gevonden.' }}</p>
                @if ($search !== '' || $typeFilter !== '' || $groupFilter || $ownOnly)
                    <x-button variant="secondary" size="sm" type="button" wire:click="resetFilters" class="mt-3">Filters wissen</x-button>
                @endif
            </div>
        @endforelse
    </div>

    <x-confirm-delete :name="$deletingName" />
</div>
