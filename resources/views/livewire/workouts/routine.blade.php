<div>
    <x-page-header :title="__('Schedule')" :description="__('Put your workouts in a fixed order. The dashboard shows whose turn it is; after finishing, the schedule moves on by itself.')">
        <x-button variant="secondary" icon="chevron-left" :href="route('workouts.index')" wire:navigate>{{ __('Workouts') }}</x-button>
    </x-page-header>

    <div class="grid max-w-3xl gap-5">
        <x-card :title="__('Order')" :padding="$this->items->isEmpty()">
            @if ($this->items->isEmpty())
                <div class="flex flex-col items-center gap-2 py-4 text-center">
                    <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"><x-icon name="repeat" /></span>
                    <p class="font-semibold">{{ __('No schedule yet') }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Add your workouts below, for example A, B, C and D.') }}</p>
                </div>
            @else
                <ol wire:sort="sort" class="divide-y divide-slate-200 dark:divide-line-dark">
                    @foreach ($this->items as $item)
                        @php($isCurrent = $this->current?->is($item))
                        @php($playable = ! $item->workout->isArchived() && $item->workout->exercises_count > 0)
                        <li wire:key="ri-{{ $item->id }}" wire:sort:item="{{ $item->id }}" style="--c: {{ $item->workout->color ?? '#1d63c5' }}"
                            @class(['flex items-center gap-3 px-3 py-3 sm:px-5', 'bg-brand-50/60 dark:bg-brand-500/10' => $isCurrent])>
                            <button type="button" wire:sort:handle aria-label="{{ __('Drag') }}"
                                    class="grid h-10 w-7 shrink-0 cursor-grab touch-none place-items-center rounded-lg text-slate-400 hover:bg-slate-200/60 hover:text-ink active:cursor-grabbing dark:hover:bg-white/10 dark:hover:text-white">
                                <x-icon name="grip" class="size-5" />
                            </button>
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-(--c) text-sm font-bold text-white">{{ $loop->iteration }}</span>
                            <div class="min-w-0 flex-1">
                                <p @class(['truncate font-semibold', 'text-slate-400 line-through' => ! $playable])>{{ $item->workout->name }}</p>
                                @if ($isCurrent)
                                    <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('Up next') }}</p>
                                @elseif (! $playable)
                                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $item->workout->isArchived() ? __('Archived: skipped') : __('No exercises: skipped') }}</p>
                                @else
                                    <button type="button" wire:click="makeNext({{ $item->id }})" class="text-sm text-slate-500 hover:text-brand-700 hover:underline dark:text-slate-400 dark:hover:text-brand-300">{{ __('Make this the next one') }}</button>
                                @endif
                            </div>
                            <x-button size="sm" variant="ghost" type="button" icon="x" class="shrink-0" :aria-label="__('Remove from schedule')"
                                      wire:click="remove({{ $item->id }})" />
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($this->items->isNotEmpty())
                <x-slot:footer>
                    <x-button variant="ghost" type="button" icon="trash" class="text-red-600 dark:text-red-400" wire:click="clear"
                              wire:confirm="{{ __('Delete the whole schedule? Your workouts themselves stay.') }}">{{ __('Delete schedule') }}</x-button>
                </x-slot:footer>
            @endif
        </x-card>

        <x-card :title="__('Add workout')">
            @if ($this->workoutOptions)
                <form wire:submit="add" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <x-form.select wire:model="workoutId" :options="$this->workoutOptions" :placeholder="__('Choose a workout')" :aria-label="__('Workout')" class="sm:flex-1" />
                    <x-button type="submit" icon="plus" loading="add">{{ __('Add') }}</x-button>
                </form>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('The same workout may appear more than once, for example A-B-A-C.') }}</p>
            @else
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Create a workout first.') }}</p>
            @endif
        </x-card>
    </div>
</div>
