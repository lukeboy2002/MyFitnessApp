<div>
    <x-page-header :title="__('Equipment')" :description="__('The standard list, plus your own equipment. Choose it with an exercise and filter on it under Exercises.')">
        <x-button icon="plus" type="button" wire:click="create">{{ __('Add equipment') }}</x-button>
    </x-page-header>

    @include('livewire.partials.master-data-messages')

    @foreach ($this->equipment->groupBy(fn ($e) => $e->isStandard() ? 'standard' : 'own') as $group => $items)
        <section wire:key="g-{{ $group }}" class="mb-6">
            <h2 class="mb-2 text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $group === 'standard' ? __('Standard') : __('Own') }}</h2>
            <ul class="divide-y divide-slate-200 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:divide-line-dark dark:bg-panel-dark dark:ring-line-dark">
                @foreach ($items as $equipment)
                    <li wire:key="eq-{{ $equipment->id }}" class="flex items-center gap-3 px-4 py-3">
                        <span @class(['grid size-10 shrink-0 place-items-center rounded-xl',
                                      'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300' => $equipment->isStandard(),
                                      'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' => ! $equipment->isStandard()])>
                            <x-icon name="weight" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $equipment->label() }}</p>
                            <a href="{{ route('exercises.index', ['materiaal' => $equipment->id]) }}" wire:navigate
                               class="text-sm text-slate-500 hover:text-brand-700 hover:underline dark:text-slate-400 dark:hover:text-brand-300">
                                {{ trans_choice(':count exercise|:count exercises', $equipment->exercises_count) }}
                            </a>
                        </div>
                        @can('update', $equipment)
                            <div class="flex shrink-0 gap-1">
                                <x-button variant="ghost" size="sm" type="button" wire:click="edit({{ $equipment->id }})">{{ __('Edit') }}</x-button>
                                <x-button variant="ghost" size="sm" icon="trash" type="button" wire:click="confirmDelete({{ $equipment->id }})" :aria-label="__('Delete')" />
                            </div>
                        @endcan
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    @if ($this->equipment->doesntContain(fn ($e) => ! $e->isStandard()))
        <button type="button" wire:click="create"
                class="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 py-4 font-semibold text-slate-500 transition hover:border-brand-400 hover:text-brand-700 dark:border-line-dark dark:text-slate-400 dark:hover:border-brand-400 dark:hover:text-brand-300">
            <x-icon name="plus" class="size-5" /> {{ __('Add your own equipment, e.g. :example', ['example' => 'Smith machine']) }}
        </button>
    @endif

    <x-modal wire:model="showForm" :title="$editingId ? __('Edit equipment') : __('New equipment')" maxWidth="md">
        <form wire:submit="save" id="equipment-form" class="grid gap-4">
            <x-form.input :label="__('Name')" wire:model="name" required maxlength="60" :placeholder="__('e.g. :example', ['example' => 'Smith machine'])" />
            <x-form.input :label="__('Order')" type="number" wire:model="sort_order" inputmode="numeric" min="0" />
        </form>
        <x-slot:footer>
            <x-button variant="ghost" type="button" wire:click="closeForm">{{ __('Cancel') }}</x-button>
            <x-button form="equipment-form" icon="check" loading="save">{{ __('Save') }}</x-button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-delete :name="$deletingName" />
</div>
