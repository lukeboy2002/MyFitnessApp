<div>
    <x-page-header title="BodyParts" description="Lichaamsdelen waar de spiergroepen onder vallen.">
        <x-button icon="plus" type="button" wire:click="create">Lichaamsdeel toevoegen</x-button>
    </x-page-header>

    @include('livewire.partials.master-data-messages')

    @if ($this->bodyParts->isEmpty())
        <x-card>
            <p class="py-6 text-center text-slate-500">Nog geen lichaamsdelen. Draai <code>php artisan db:seed --class=BodyPartSeeder</code> voor de standaardlijst.</p>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->bodyParts as $bodyPart)
                <article wire:key="bp-{{ $bodyPart->id }}"
                         class="flex overflow-hidden rounded-2xl sm:flex-col bg-white shadow-sm ring-1 ring-slate-200 dark:bg-panel-dark dark:ring-line-dark">
                    <div class="relative flex h-36 w-32 shrink-0 items-end justify-center overflow-hidden bg-[radial-gradient(circle_at_50%_35%,white,var(--color-canvas)_70%)] sm:h-48 sm:w-auto dark:bg-[radial-gradient(circle_at_50%_35%,#1b2a45,var(--color-canvas-dark)_70%)]">
                        @if ($picture = $bodyPart->imageUrl())
                            <img src="{{ $picture }}" alt="" loading="lazy" class="max-h-[88%] max-w-[85%] object-contain object-bottom drop-shadow-[0_6px_10px_rgba(0,30,70,.18)]">
                        @else
                            <span class="mb-auto mt-auto grid size-16 place-items-center rounded-2xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                                <x-icon :name="$bodyPart->icon ?? 'body'" class="size-9" />
                            </span>
                        @endif
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-3 p-4 sm:p-5">
                        <div class="flex flex-wrap items-start gap-2">
                            <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                                <x-icon :name="$bodyPart->icon ?? 'body'" class="size-5" />
                            </span>
                            <h2 class="grow self-center font-display text-lg sm:text-xl font-bold uppercase tracking-wide">{{ $bodyPart->name }}</h2>
                            <x-badge :color="$bodyPart->isStandard() ? 'gray' : 'brand'">{{ $bodyPart->isStandard() ? 'Standaard' : 'Eigen' }}</x-badge>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($bodyPart->muscleGroups as $group)
                                <x-badge :hex="$group->color">{{ $group->name }}</x-badge>
                            @empty
                                <span class="text-sm text-slate-500 dark:text-slate-400">Nog geen spiergroepen</span>
                            @endforelse
                        </div>
                        @can('update', $bodyPart)
                            <div class="mt-auto flex justify-end gap-1 pt-1">
                                <x-button variant="ghost" size="sm" type="button" wire:click="edit({{ $bodyPart->id }})">Bewerken</x-button>
                                <x-button variant="ghost" size="sm" icon="trash" type="button" wire:click="delete({{ $bodyPart->id }})"
                                          wire:confirm="{{ $bodyPart->name }} verwijderen?" aria-label="Verwijderen" />
                            </div>
                        @endcan
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <x-modal wire:model="showForm" :title="$editingId ? 'Lichaamsdeel bewerken' : 'Nieuw lichaamsdeel'" max-width="md">
        <form wire:submit="save" id="body-part-form" class="grid gap-4 sm:grid-cols-3">
            <x-form.input label="Naam" wire:model="name" required class="sm:col-span-2" placeholder="Bijv. Bovenlichaam" />
            <x-form.input label="Volgorde" type="number" wire:model="sort_order" inputmode="numeric" min="0" />
            <x-form.select label="Icoon" wire:model.live="icon" :options="\App\Livewire\BodyParts\Index::ICONS" class="sm:col-span-2" />
            <div class="flex items-end">
                <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                    <x-icon :name="$icon ?: 'body'" class="size-6" />
                </span>
            </div>
            <x-form.image-upload label="Plaatje" wire:model="image" class="sm:col-span-3" hint="PNG met transparante achtergrond werkt het mooist · max 2 MB"
                                 :preview="$image?->temporaryUrl() ?? $currentImage" remove="deleteImage" />
        </form>
        <x-slot:footer>
            <x-button variant="ghost" type="button" wire:click="closeForm">Annuleren</x-button>
            <x-button form="body-part-form" icon="check" loading="save">Opslaan</x-button>
        </x-slot:footer>
    </x-modal>
</div>
