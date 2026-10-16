@php
    $primary = $exercise->primaryMuscleGroup();
    $secondary = $exercise->secondaryMuscleGroups();
@endphp

<div>
    <a href="{{ route('exercises.index') }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
        <x-icon name="chevron-right" class="size-4 rotate-180" /> Alle oefeningen
    </a>

    <x-page-header :title="$exercise->name" :description="$exercise->equipment?->label()">
        @can('update', $exercise)
            <x-button variant="secondary" :href="route('exercises.edit', $exercise)" wire:navigate>Bewerken</x-button>
        @endcan
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-5">{{ session('status') }}</x-alert>
    @endif
    @if ($exercise->isArchived())
        <x-alert type="warning" class="mb-5">Deze oefening is gearchiveerd en staat niet meer in je lijst.</x-alert>
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

            <x-card>
                <dl class="grid gap-4 text-sm">
                    <div>
                        <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">Type</dt>
                        <dd><x-badge :color="$exercise->type->color()" :icon="$exercise->type->icon()">{{ $exercise->type->label() }}</x-badge></dd>
                    </div>
                    @if ($primary)
                        <div>
                            <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">Primaire spiergroep</dt>
                            <dd><x-badge :hex="$primary->color">{{ $primary->name }}</x-badge></dd>
                        </div>
                    @endif
                    @if ($secondary->isNotEmpty())
                        <div>
                            <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">Secundair</dt>
                            <dd class="flex flex-wrap gap-1.5">
                                @foreach ($secondary as $group)
                                    <x-badge color="gray">{{ $group->name }}</x-badge>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if ($target = $exercise->defaultTarget())
                        <div>
                            <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">Standaarddoel</dt>
                            <dd class="font-semibold">{{ $target }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">Bron</dt>
                        <dd>{{ $exercise->isStandard() ? 'Standaardlijst' : 'Eigen oefening' }}</dd>
                    </div>
                </dl>
            </x-card>
        </div>

        <div class="flex min-w-0 flex-col gap-5">
            @if ($exercise->description)
                <x-card title="Omschrijving">
                    <p class="leading-relaxed">{{ $exercise->description }}</p>
                </x-card>
            @endif

            @if ($exercise->instructions)
                <x-card title="Uitvoering">
                    <ol class="grid gap-3">
                        @foreach ($exercise->instructions as $step)
                            <li class="flex gap-3">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-sm font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $loop->iteration }}</span>
                                <span class="pt-0.5 leading-relaxed">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </x-card>
            @endif

            @if ($exercise->tips)
                <x-card title="Tips">
                    <ul class="grid gap-2">
                        @foreach ($exercise->tips as $tip)
                            <li class="flex gap-2.5">
                                <x-icon name="check" class="mt-0.5 size-5 text-emerald-600 dark:text-emerald-400" />
                                <span class="leading-relaxed">{{ $tip }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if (! $exercise->description && ! $exercise->instructions && ! $exercise->tips)
                <x-card>
                    <p class="text-slate-500 dark:text-slate-400">Nog geen omschrijving of uitvoering toegevoegd.</p>
                </x-card>
            @endif
        </div>
    </div>
</div>
