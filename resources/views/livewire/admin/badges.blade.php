<div>
    <x-page-header :title="__('Badge management')" :description="__('The badges and their levels for all users. What someone has earned is recalculated every time, so a new threshold applies right away.')">
        <x-button icon="plus" type="button" wire:click="create">{{ __('Add badge') }}</x-button>
    </x-page-header>

    <x-card :padding="false">
        <ul class="divide-y divide-slate-200 dark:divide-line-dark">
            @foreach ($this->badges as $badge)
                <li wire:key="badge-{{ $badge->id }}" @class(['flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6', 'opacity-60' => ! $badge->is_active])>
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-200">
                        <x-icon :name="$badge->icon" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-semibold">
                            {{ $badge->name }}
                            @unless ($badge->is_active) <x-badge color="gray">{{ __('Off') }}</x-badge> @endunless
                        </p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $badge->description() }}</p>
                        <p class="mt-0.5 text-sm tabular-nums">
                            {{ collect($badge->thresholds)->map(fn ($t) => $badge->metric->format($t))->implode(' · ') }}
                            <span class="text-slate-500 dark:text-slate-400">{{ $badge->metric->unit() }}</span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <x-button variant="ghost" size="sm" type="button" wire:click="toggleActive({{ $badge->id }})">{{ $badge->is_active ? __('Turn off') : __('Turn on') }}</x-button>
                        <x-button variant="secondary" size="sm" type="button" wire:click="edit({{ $badge->id }})">{{ __('Edit') }}</x-button>
                        <x-button variant="ghost" size="sm" type="button" icon="trash" wire:click="confirmDelete({{ $badge->id }})" :aria-label="__('Delete')" />
                    </div>
                </li>
            @endforeach
        </ul>
    </x-card>

    <x-modal wire:model="showForm" :title="$editingId ? __('Edit badge') : __('New badge')" maxWidth="xl">
        <form wire:submit="save" id="badge-form" class="grid gap-4 sm:grid-cols-2">
            <x-form.input :label="__('Name')" wire:model="name" required :placeholder="__('e.g. :example', ['example' => __('Treadmill king')])" />
            <x-form.select :label="__('Icon')" wire:model="icon" :options="array_map(fn ($label) => __($label), \App\Models\Badge::ICONS)" />

            <x-form.select :label="__('What counts')" wire:model.live="metric" :options="\App\Enums\BadgeMetric::options()" class="sm:col-span-2" />
            @if (\App\Enums\BadgeMetric::tryFrom($metric)?->needsExercise())
                <x-form.select :label="__('Exercise')" wire:model="exercise_id" :options="$this->exercises" :placeholder="__('Choose an exercise…')" class="sm:col-span-2" required />
            @endif

            <x-form.input :label="__('Thresholds')" wire:model.live.debounce.400ms="thresholds" class="sm:col-span-2" required placeholder="1, 10, 25, 50"
                          :suffix="\App\Enums\BadgeMetric::tryFrom($metric)?->unit()"
                          :hint="__('From low to high, separated by commas. Every number is a level (bronze, silver, gold, platinum, diamond…). Decimals with a point: 21.1')" />
            <x-form.input :label="__('Name per level')" wire:model.live.debounce.400ms="label" required :placeholder="__('{n} trainings')" :hint="__('{n} becomes the number')" />
            <x-form.input :label="__('Name of first level')" wire:model.live.debounce.400ms="first_label" :placeholder="__('e.g. :example', ['example' => __('Eerste training')])" :hint="__('Optional')" />
            <x-form.input :label="__('Order')" type="number" wire:model="sort_order" inputmode="numeric" min="0" />
            <div class="self-end pb-2"><x-form.toggle wire:model="is_active" :label="__('Active')" /></div>

            @if ($this->preview)
                <div class="sm:col-span-2">
                    <p class="text-sm font-semibold">{{ __('Levels') }}</p>
                    <ol class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($this->preview as $level => $title)
                            <li class="rounded-lg bg-slate-100 px-2 py-0.5 text-sm dark:bg-white/10">
                                <span class="text-slate-500 dark:text-slate-400">{{ __(ucfirst(\App\Support\BadgeProgress::TIERS[min($level, 4)])) }}:</span> {{ $title }}
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </form>
        <x-slot:footer>
            <x-button variant="ghost" type="button" wire:click="$set('showForm', false)">{{ __('Cancel') }}</x-button>
            <x-button form="badge-form" icon="check" loading="save">{{ __('Save') }}</x-button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-delete :name="$deletingName" :title="__('Delete badge?')" />
</div>
