<div>
    <x-page-header :title="__('Muscles')" :description="__('Muscles per muscle group, with Latin name.')">
        <x-button icon="plus" type="button" wire:click="create">{{ __('Add muscle') }}</x-button>
    </x-page-header>

    @include('livewire.partials.master-data-messages')

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:max-w-2xl">
        <x-form.input wire:model.live.debounce.300ms="search" icon="search" :placeholder="__('Search by name or Latin name')" :aria-label="__('Search')" />
        <x-form.select wire:model.live="groupFilter" :placeholder="__('All muscle groups')" :aria-label="__('Muscle group')"
                       :options="$this->muscleGroups->pluck('name', 'id')" />
    </div>

    <div class="flex flex-col gap-4">
        @forelse ($this->muscles as $muscles)
            @php $group = $muscles->first()->muscleGroup; @endphp
            <x-card :padding="false" wire:key="g-{{ $group->id }}">
                <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-3 sm:px-6 dark:border-line-dark">
                    <span class="size-3 rounded-full" style="background-color: {{ $group->color }}"></span>
                    <h2 class="font-display text-lg font-bold uppercase tracking-wide">{{ $group->name }}</h2>
                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ $muscles->count() }}</span>
                </div>
                <ul class="divide-y divide-slate-200 dark:divide-line-dark">
                    @foreach ($muscles as $muscle)
                        <li wire:key="m-{{ $muscle->id }}" class="flex items-center gap-3 px-5 py-3 sm:px-6">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ $muscle->name }}
                                    @unless ($muscle->isStandard()) <x-badge class="ml-1">{{ __('Own') }}</x-badge> @endunless
                                </p>
                                @if ($muscle->latin_name)
                                    <p class="truncate text-sm italic text-slate-500 dark:text-slate-400">{{ $muscle->latin_name }}</p>
                                @endif
                            </div>
                            @can('update', $muscle)
                                <div class="flex shrink-0 gap-1">
                                    <x-button variant="ghost" size="sm" type="button" wire:click="edit({{ $muscle->id }})">{{ __('Edit') }}</x-button>
                                    <x-button variant="ghost" size="sm" icon="trash" type="button" wire:click="confirmDelete({{ $muscle->id }})" :aria-label="__('Delete')" />
                                </div>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @empty
            <x-card><p class="text-center text-slate-500">{{ __('No muscles found.') }}</p></x-card>
        @endforelse
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? __('Edit muscle') : __('New muscle')">
        <form wire:submit="save" id="muscle-form" class="grid gap-4 sm:grid-cols-2">
            <x-form.input :label="__('Name')" wire:model="name" required :placeholder="__('e.g. :example', ['example' => __('Front deltoid')])" />
            <x-form.input :label="__('Latin name (label)')" wire:model="latin_name" :placeholder="__('e.g. :example', ['example' => 'Deltoideus anterior'])" />
            <x-form.select :label="__('Muscle group')" wire:model="muscle_group_id" required>
                @foreach ($this->muscleGroups->groupBy(fn ($g) => $g->bodyPart->name) as $bodyPartName => $groups)
                    <optgroup label="{{ $bodyPartName }}">
                        @foreach ($groups as $g)
                            <option value="{{ $g->id }}">{{ $g->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-form.select>
            <x-form.input :label="__('Order')" type="number" wire:model="sort_order" inputmode="numeric" min="0" />
        </form>
        <x-slot:footer>
            <x-button variant="ghost" type="button" wire:click="closeForm">{{ __('Cancel') }}</x-button>
            <x-button form="muscle-form" icon="check" loading="save">{{ __('Save') }}</x-button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-delete :name="$deletingName" />
</div>
