<div>
    <x-page-header title="BodyParts" description="Lichaamsdelen waar de spiergroepen onder vallen.">
        <x-button icon="plus" type="button" wire:click="create">Lichaamsdeel toevoegen</x-button>
    </x-page-header>

    @include('livewire.partials.master-data-messages')

    <x-card :padding="false">
        <ul class="divide-y divide-slate-200 dark:divide-line-dark">
            @forelse ($this->bodyParts as $bodyPart)
                <li wire:key="bp-{{ $bodyPart->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 sm:px-6">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="body" class="size-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-semibold">
                            {{ $bodyPart->name }}
                            <x-badge :color="$bodyPart->isStandard() ? 'gray' : 'brand'">{{ $bodyPart->isStandard() ? 'Standaard' : 'Eigen' }}</x-badge>
                        </p>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            @forelse ($bodyPart->muscleGroups as $group)
                                <x-badge :hex="$group->color">{{ $group->name }}</x-badge>
                            @empty
                                <span class="text-sm text-slate-500 dark:text-slate-400">Nog geen spiergroepen</span>
                            @endforelse
                        </div>
                    </div>
                    @can('update', $bodyPart)
                        <div class="flex gap-1">
                            <x-button variant="ghost" size="sm" type="button" wire:click="edit({{ $bodyPart->id }})">Bewerken</x-button>
                            <x-button variant="ghost" size="sm" icon="trash" type="button" wire:click="delete({{ $bodyPart->id }})"
                                      wire:confirm="{{ $bodyPart->name }} verwijderen?" aria-label="Verwijderen" />
                        </div>
                    @endcan
                </li>
            @empty
                <li class="px-6 py-10 text-center text-slate-500">Nog geen lichaamsdelen. Draai <code>php artisan db:seed --class=BodyPartSeeder</code> voor de standaardlijst.</li>
            @endforelse
        </ul>
    </x-card>

    <x-modal wire:model="showForm" :title="$editingId ? 'Lichaamsdeel bewerken' : 'Nieuw lichaamsdeel'" max-width="md">
        <form wire:submit="save" id="body-part-form" class="grid gap-4 sm:grid-cols-3">
            <x-form.input label="Naam" wire:model="name" required class="sm:col-span-2" placeholder="Bijv. Bovenlichaam" />
            <x-form.input label="Volgorde" type="number" wire:model="sort_order" inputmode="numeric" min="0" />
        </form>
        <x-slot:footer>
            <x-button variant="ghost" type="button" wire:click="closeForm">Annuleren</x-button>
            <x-button form="body-part-form" icon="check" loading="save">Opslaan</x-button>
        </x-slot:footer>
    </x-modal>
</div>
