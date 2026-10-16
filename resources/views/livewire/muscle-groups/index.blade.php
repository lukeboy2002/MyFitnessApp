<div>
    <x-page-header title="MuscleGroups" description="Spiergroepen, met een eigen kleur voor badges bij oefeningen en workouts.">
        <x-button icon="plus" type="button" wire:click="create">Spiergroep toevoegen</x-button>
    </x-page-header>

    @include('livewire.partials.master-data-messages')

    <div class="mb-5 flex flex-wrap gap-2">
        <button type="button" wire:click="$set('bodyPartFilter', null)"
                @class(['rounded-full px-4 py-2 text-sm font-semibold ring-1 ring-inset transition',
                        'bg-brand-700 text-white ring-brand-700 dark:bg-brand-500 dark:ring-brand-500' => ! $bodyPartFilter,
                        'bg-white ring-slate-300 hover:bg-slate-50 dark:bg-white/5 dark:ring-line-dark' => $bodyPartFilter])>Alles</button>
        @foreach ($this->bodyParts as $bodyPart)
            <button type="button" wire:click="$set('bodyPartFilter', {{ $bodyPart->id }})" wire:key="f-{{ $bodyPart->id }}"
                    @class(['rounded-full px-4 py-2 text-sm font-semibold ring-1 ring-inset transition',
                            'bg-brand-700 text-white ring-brand-700 dark:bg-brand-500 dark:ring-brand-500' => $bodyPartFilter === $bodyPart->id,
                            'bg-white ring-slate-300 hover:bg-slate-50 dark:bg-white/5 dark:ring-line-dark' => $bodyPartFilter !== $bodyPart->id])>{{ $bodyPart->name }}</button>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->muscleGroups as $group)
            <article wire:key="mg-{{ $group->id }}" style="--c: {{ $group->color }}"
                     class="flex overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 sm:flex-col dark:bg-panel-dark dark:ring-line-dark">
                <div class="flex h-32 w-32 shrink-0 items-end justify-center overflow-hidden bg-[radial-gradient(circle_at_50%_30%,white,color-mix(in_oklab,var(--c)_22%,var(--color-canvas))_80%)] sm:h-40 sm:w-auto dark:bg-[radial-gradient(circle_at_50%_30%,#1b2a45,color-mix(in_oklab,var(--c)_30%,var(--color-canvas-dark))_85%)]">
                    @if ($picture = $group->imageUrl())
                        <img src="{{ $picture }}" alt="" loading="lazy" class="max-h-[88%] max-w-[85%] object-contain object-bottom drop-shadow-[0_6px_10px_rgba(0,30,70,.18)]">
                    @else
                        <span class="my-auto grid size-14 place-items-center rounded-2xl text-white shadow-sm" style="background-color: {{ $group->color }}">
                            <x-icon :name="$group->icon ?? 'arm'" class="size-7" />
                        </span>
                    @endif
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-2 p-4 sm:p-5">
                    <div class="flex flex-wrap items-start gap-2">
                        <span class="grid size-8 shrink-0 place-items-center rounded-xl text-white" style="background-color: {{ $group->color }}">
                            <x-icon :name="$group->icon ?? 'arm'" class="size-5" />
                        </span>
                        <h3 class="grow self-center font-display text-lg font-bold uppercase sm:text-xl">{{ $group->name }}</h3>
                        <x-badge :color="$group->isStandard() ? 'gray' : 'brand'">{{ $group->isStandard() ? 'Standaard' : 'Eigen' }}</x-badge>
                    </div>
                    <p class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                        <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $group->color }}"></span>
                        {{ $group->bodyPart->name }} · {{ $group->muscles_count }} {{ $group->muscles_count === 1 ? 'spier' : 'spieren' }}
                    </p>
                    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2">
                        <a href="{{ route('muscles.index', ['groep' => $group->id]) }}" wire:navigate class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Spieren bekijken</a>
                        @can('update', $group)
                            <div class="flex gap-1">
                                <x-button variant="ghost" size="sm" type="button" wire:click="edit({{ $group->id }})">Bewerken</x-button>
                                <x-button variant="ghost" size="sm" icon="trash" type="button" wire:click="confirmDelete({{ $group->id }})" aria-label="Verwijderen" />
                            </div>
                        @endcan
                    </div>
                </div>
            </article>
        @empty
            <p class="text-slate-500 sm:col-span-2 xl:col-span-3">Geen spiergroepen gevonden.</p>
        @endforelse
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Spiergroep bewerken' : 'Nieuwe spiergroep'">
        <form wire:submit="save" id="muscle-group-form" class="grid gap-4 sm:grid-cols-2">
            <x-form.input label="Naam" wire:model="name" required placeholder="Bijv. Onderrug" />
            <x-form.select label="Lichaamsdeel" wire:model="body_part_id" :options="$this->bodyParts->pluck('name', 'id')" required />

            <x-form.field label="Kleur" name="color" class="sm:col-span-2">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach (\App\Livewire\MuscleGroups\Index::COLORS as $swatch)
                        <button type="button" wire:click="$set('color', '{{ $swatch }}')" aria-label="Kleur {{ $swatch }}"
                                style="background-color: {{ $swatch }}"
                                @class(['size-9 rounded-full ring-offset-2 transition dark:ring-offset-panel-dark',
                                        'ring-2 ring-ink dark:ring-white' => strtolower($color) === $swatch])></button>
                    @endforeach
                    <input type="color" wire:model.live="color" aria-label="Eigen kleur"
                           class="size-9 cursor-pointer rounded-full border-0 bg-transparent p-0 [&::-webkit-color-swatch]:rounded-full [&::-webkit-color-swatch-wrapper]:p-0">
                </div>
            </x-form.field>

            <x-form.select label="Icoon" wire:model.live="icon" :options="\App\Livewire\MuscleGroups\Index::ICONS" />
            <x-form.input label="Volgorde" type="number" wire:model="sort_order" inputmode="numeric" min="0" />

            <div class="flex items-center gap-3 rounded-2xl bg-slate-50 p-3 sm:col-span-2 dark:bg-white/5">
                <span class="grid size-12 place-items-center rounded-2xl text-white" style="background-color: {{ $color }}">
                    <x-icon :name="$icon ?: 'arm'" class="size-6" />
                </span>
                <span class="text-sm text-slate-500 dark:text-slate-400">Voorbeeld</span>
                <x-badge :hex="$color">{{ $name ?: 'Spiergroep' }}</x-badge>
            </div>

            <x-form.image-upload label="Plaatje" wire:model="image" class="sm:col-span-2" hint="PNG met transparante achtergrond werkt het mooist · max 2 MB"
                                 :preview="$image?->temporaryUrl() ?? $currentImage" remove="deleteImage" />
        </form>
        <x-slot:footer>
            <x-button variant="ghost" type="button" wire:click="closeForm">Annuleren</x-button>
            <x-button form="muscle-group-form" icon="check" loading="save">Opslaan</x-button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-delete :name="$deletingName" />
</div>
