@php
    $primary = $exercise->primaryMuscleGroup();
    $secondary = $exercise->secondaryMuscleGroups();
@endphp

<div>
    <a href="{{ route('exercises.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
        <x-icon name="chevron-right" class="size-4 rotate-180" /> {{ __('All exercises') }}
    </a>

    <x-page-header :title="$exercise->name" :description="$exercise->equipment?->label()">
        @can('update', $exercise)
            <x-button variant="secondary" :href="route('exercises.edit', $exercise)" wire:navigate>{{ __('Edit') }}</x-button>
        @endcan
    </x-page-header>

    @if ($exercise->isArchived())
        <x-alert type="warning" class="mb-5">{{ __('This exercise is archived and no longer in your list.') }}</x-alert>
    @endif

    <div class="grid gap-5 lg:grid-cols-[18rem_1fr]">
        <div class="flex flex-col gap-5">
            <div style="--c: {{ $primary?->color ?? '#1d63c5' }}"
                 @class(['grid aspect-square place-items-center overflow-hidden rounded-2xl shadow-sm ring-1 ring-slate-200 dark:ring-line-dark',
                         'bg-white' => $exercise->image_path,
                         'bg-[radial-gradient(circle_at_50%_30%,white,color-mix(in_oklab,var(--c)_22%,var(--color-canvas))_80%)] dark:bg-[radial-gradient(circle_at_50%_30%,#1b2a45,color-mix(in_oklab,var(--c)_30%,var(--color-canvas-dark))_85%)]' => ! $exercise->image_path])>
                @if ($picture = $exercise->imageUrl())
                    <img src="{{ $picture }}" alt="{{ $exercise->name }}" @class(['size-full object-contain', 'p-6' => ! $exercise->image_path])>
                @else
                    <x-icon :name="$exercise->type->icon()" class="size-16 text-(--c)" />
                @endif
            </div>

            <x-card :title="__('Machine setting')">
                <p class="mb-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Your own setting, you will see it again during every training.') }}</p>
                <livewire:exercises.setting :exercise="$exercise" />
            </x-card>

            <x-card>
                <dl class="grid gap-4 text-sm">
                    <div>
                        <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">{{ __('Type') }}</dt>
                        <dd><x-badge :color="$exercise->type->color()" :icon="$exercise->type->icon()">{{ $exercise->type->label() }}</x-badge></dd>
                    </div>
                    @if ($primary)
                        <div>
                            <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">{{ __('Primary muscle group') }}</dt>
                            <dd><x-badge :hex="$primary->color">{{ $primary->name }}</x-badge></dd>
                        </div>
                    @endif
                    @if ($secondary->isNotEmpty())
                        <div>
                            <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">{{ __('Secondary') }}</dt>
                            <dd class="flex flex-wrap gap-1.5">
                                @foreach ($secondary as $group)
                                    <x-badge color="gray">{{ $group->name }}</x-badge>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if ($target = $exercise->defaultTarget())
                        <div>
                            <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">{{ __('Default target') }}</dt>
                            <dd class="font-semibold">{{ $target }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">{{ __('Source') }}</dt>
                        <dd>{{ $exercise->isStandard() ? __('Standard list') : __('Own exercise') }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card :title="__('Progress (noun)')">
                @if ($timesDone)
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('You did this exercise :count× in a completed training.', ['count' => $timesDone]) }}</p>
                    <x-button variant="secondary" icon="chart" size="sm" :href="route('progress.exercise', $exercise)" wire:navigate class="mt-3">{{ __('View progress') }}</x-button>
                @else
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Not done yet. After your first training you will see your progress here.') }}</p>
                @endif
            </x-card>
        </div>

        <div class="flex min-w-0 flex-col gap-5">
            {{-- Standaardoefeningen in de taal van de gebruiker (App\Support\ExerciseText) --}}
            @php($text = \App\Support\ExerciseText::for($exercise))

            @if ($text['description'])
                <x-card :title="__('Description')">
                    <p class="leading-relaxed">{{ $text['description'] }}</p>
                </x-card>
            @endif

            @if ($text['instructions'])
                <x-card :title="__('Instructions')">
                    <ol class="grid gap-3">
                        @foreach ($text['instructions'] as $step)
                            <li class="flex gap-3">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-sm font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $loop->iteration }}</span>
                                <span class="pt-0.5 leading-relaxed">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </x-card>
            @endif

            @if ($text['tips'])
                <x-card :title="__('Tips')">
                    <ul class="grid gap-2">
                        @foreach ($text['tips'] as $tip)
                            <li class="flex gap-2.5">
                                <x-icon name="check" class="mt-0.5 size-5 text-emerald-600 dark:text-emerald-400" />
                                <span class="leading-relaxed">{{ $tip }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if (! $text['description'] && ! $text['instructions'] && ! $text['tips'])
                <x-card>
                    <p class="text-slate-500 dark:text-slate-400">{{ __('No description or instructions added yet.') }}</p>
                </x-card>
            @endif
        </div>
    </div>
</div>
