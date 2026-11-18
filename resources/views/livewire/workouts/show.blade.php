<div style="--c: {{ $workout->color ?? '#1d63c5' }}">
    @php($ready = $workout->isStandard())
    <a href="{{ route('workouts.index', $incoming ? ['ontvangen' => 1] : ($ready ? ['kant-en-klaar' => 1] : ($workout->isArchived() ? ['archief' => 1] : []))) }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
        <x-icon name="chevron-right" class="size-4 rotate-180" /> {{ $incoming ? __('Received workouts') : ($ready ? __('Ready-made workouts') : __('All workouts')) }}
    </a>

    <x-page-header :title="$workout->name" :description="$workout->description">
        @if ($canManage)
            <x-button variant="secondary" type="button" icon="archive" wire:click="archive" loading="archive">
                {{ $workout->isArchived() ? __('Restore') : __('Archive') }}
            </x-button>
            <x-button variant="secondary" :href="route('workouts.edit', $workout)" wire:navigate>{{ __('Edit') }}</x-button>
        @endif
        @can('share', $workout)
            <x-button variant="secondary" type="button" icon="share" wire:click="openShare">{{ __('Share') }}</x-button>
        @endcan
        @if ($incoming)
            <x-button variant="secondary" type="button" wire:click="declineShare({{ $incoming->id }})" loading="declineShare">{{ __('Decline') }}</x-button>
            <x-button icon="plus" type="button" wire:click="acceptShare({{ $incoming->id }})" loading="acceptShare">{{ __('Add to my workouts') }}</x-button>
        @elseif ($ready)
            <x-button icon="plus" type="button" wire:click="adopt" loading="adopt" :disabled="$workout->isArchived()">{{ __('Add to my workouts') }}</x-button>
        @elseif ($running)
            <x-button variant="fire" icon="play" :href="route('sessions.run', $running)" wire:navigate>{{ __('Continue training') }}</x-button>
        @else
            <x-button variant="fire" icon="play" type="button" wire:click="start" loading="start" :disabled="$workout->isArchived() || $workout->exercises->isEmpty()">{{ __('Start workout') }}</x-button>
        @endif
    </x-page-header>

    @if ($incoming)
        <x-alert type="info" :timeout="0" :dismissible="false" class="mb-5">
            {{ __(':name sent you this workout.', ['name' => $incoming->sender->name]) }}
            @if ($incoming->message) <span class="italic">“{{ $incoming->message }}”</span> @endif
            {{ __('Add it to your workouts to start it or to change it to your liking.') }}
            @if ($ownExercises->isNotEmpty())
                <span class="mt-1 block text-sm">{{ trans_choice('Your own copy is made of :count exercise of :name: :list.|Your own copies are made of :count exercises of :name: :list.', $ownExercises->count(), ['name' => $incoming->sender->name, 'list' => $ownExercises->implode(', ')]) }}</span>
            @endif
        </x-alert>
    @elseif ($ready)
        <x-alert type="info" :timeout="0" class="mb-5">
            {{ __('This is a ready-made workout. Add it to your workouts to start it or to change it to your liking.') }}
            @if ($copy)
                {{ __('You already have it:') }} <a href="{{ route('workouts.show', $copy) }}" wire:navigate class="font-semibold underline">{{ $copy->name }}</a>.
            @endif
        </x-alert>
    @elseif ($running && $running->workout_id !== $workout->id)
        <x-alert type="info" :timeout="0" class="mb-5">{{ __('You still have a training running:') }} <a href="{{ route('sessions.run', $running) }}" wire:navigate class="font-semibold underline">{{ $running->name }}</a>. {{ __('Finish that one first.') }}</x-alert>
    @endif
    @if ($workout->isArchived())
        <x-alert type="warning" class="mb-5">{{ $ready ? __('This ready-made workout is archived and hidden for users.') : __('This workout is archived. Restore it to be able to start it again.') }}</x-alert>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_18rem]">
        <x-card :padding="false" class="overflow-hidden">
            <ol class="divide-y divide-slate-100 dark:divide-line-dark">
                @foreach ($workout->exercises as $item)
                    @php($exercise = $item->exercise)
                    @php($primary = $exercise->primaryMuscleGroup())
                    @php($inSuperset = $item->superset_next || ($loop->index > 0 && $workout->exercises[$loop->index - 1]->superset_next))
                    <li wire:key="i-{{ $item->id }}" style="--g: {{ $primary?->color ?? '#1d63c5' }}"
                        @class(['flex items-center gap-4 px-4 py-3 sm:px-5', 'border-l-4 border-violet-500 bg-violet-50/50 pl-3 sm:pl-4 dark:bg-violet-500/10' => $inSuperset])>
                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-(--c) text-sm font-bold text-white">{{ $loop->iteration }}</span>
                        <div class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-[radial-gradient(circle_at_50%_30%,white,color-mix(in_oklab,var(--g)_22%,var(--color-canvas))_80%)] dark:bg-[radial-gradient(circle_at_50%_30%,#1b2a45,color-mix(in_oklab,var(--g)_30%,var(--color-canvas-dark))_85%)]">
                            @if ($picture = $exercise->imageUrl())
                                <img src="{{ $picture }}" alt="" loading="lazy" class="size-full object-contain p-1">
                            @else
                                <x-icon :name="$exercise->type->icon()" class="size-6 text-(--g)" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            @if ($exercise->isStandard() || $exercise->isOwnedBy(auth()->user()))
                                <a href="{{ route('exercises.show', $exercise) }}" wire:navigate class="font-semibold hover:underline">{{ $exercise->name }}</a>
                            @else
                                <span class="font-semibold">{{ $exercise->name }}</span>
                            @endif
                            <p class="text-sm text-slate-600 dark:text-slate-300">
                                <span class="font-semibold text-ink dark:text-white">{{ $item->targetSummary() ?? __('No target') }}</span>
                                @if ($item->superset_next) <span class="text-violet-700 dark:text-violet-300">· {{ __('superset with the next one') }}</span>
                                @elseif ($item->rest_seconds && $exercise->type->usesSets()) <span class="text-slate-400">· {{ __('rest :time', ['time' => \App\Models\WorkoutExercise::duration($item->rest_seconds)]) }}</span> @endif
                            </p>
                            @if ($item->notes)
                                <p class="mt-0.5 text-sm text-slate-500 italic dark:text-slate-400">{{ $item->notes }}</p>
                            @endif
                        </div>
                        <x-badge :color="$exercise->type->color()" :icon="$exercise->type->icon()" class="hidden sm:inline-flex">{{ $exercise->type->label() }}</x-badge>
                    </li>
                @endforeach
            </ol>
        </x-card>

        <x-card>
            <dl class="grid gap-4 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Number of exercises') }}</dt>
                        <dd class="font-display text-2xl font-bold">{{ $workout->exercises->count() }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Duration') }}</dt>
                        <dd class="font-display text-2xl font-bold">± {{ $workout->minutes() ?? '–' }} <span class="text-base">min</span></dd>
                    </div>
                </div>
                @if ($workout->muscleGroups->isNotEmpty())
                    <div>
                        <dt class="mb-1.5 font-semibold text-slate-500 dark:text-slate-400">{{ __('Muscle groups') }}</dt>
                        <dd class="flex flex-wrap gap-1.5">
                            @foreach ($workout->muscleGroups as $group)
                                <x-badge :hex="$group->color">{{ $group->name }}</x-badge>
                            @endforeach
                        </dd>
                    </div>
                @endif
                @php($sets = $workout->exercises->filter(fn ($i) => $i->exercise->type->usesSets())->sum('target_sets'))
                @if ($sets)
                    <div>
                        <dt class="mb-1 font-semibold text-slate-500 dark:text-slate-400">{{ __('Total sets') }}</dt>
                        <dd class="font-semibold">{{ $sets }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>
    </div>

    @can('share', $workout)
        <x-modal wire:model="showShare" :title="__('Share :name', ['name' => $workout->name])" maxWidth="md">
            @if ($this->buddies->isEmpty())
                <p class="text-slate-600 dark:text-slate-300">{{ __('You have no buddies yet. Invite someone first; then you can send them your workouts.') }}</p>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Only buddies with a public profile can receive workouts.') }}</p>
                <x-button class="mt-4" icon="users" :href="route('buddies')" wire:navigate>{{ __('Buddies') }}</x-button>
            @else
                <form wire:submit="share" id="share-form" class="grid gap-4">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Choose who. They get a copy; your workout stays yours.') }}</p>
                    <div class="grid gap-2">
                        @foreach ($this->buddies as $buddy)
                            <label wire:key="sb-{{ $buddy->id }}" class="flex cursor-pointer items-center gap-3 rounded-xl p-3 ring-1 ring-slate-200 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-500 dark:ring-line-dark dark:has-checked:bg-brand-500/15">
                                <input type="checkbox" wire:model="shareTo" value="{{ $buddy->id }}" class="size-5 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                                <x-avatar :user="$buddy" class="size-8 ring-0" />
                                <span class="font-semibold">{{ $buddy->name }}</span>
                                <span class="ml-auto text-sm text-slate-400">{{ '@'.$buddy->username }}</span>
                            </label>
                        @endforeach
                        <x-form.error name="shareTo" />
                    </div>
                    <x-form.textarea :label="__('Message (optional)')" wire:model="shareMessage" rows="2" maxlength="300" />
                    @if ($ownExercises->isNotEmpty())
                        <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-white/5 dark:text-slate-300">
                            {{ trans_choice(':count own exercise (:list) is copied along, so your buddy has it too.|:count own exercises (:list) are copied along, so your buddy has them too.', $ownExercises->count(), ['list' => $ownExercises->implode(', ')]) }}
                        </p>
                    @endif
                </form>
                <x-slot:footer>
                    <x-button variant="ghost" type="button" x-on:click="show = false">{{ __('Cancel') }}</x-button>
                    <x-button form="share-form" icon="share" loading="share">{{ __('Send') }}</x-button>
                </x-slot:footer>
            @endif
        </x-modal>
    @endcan
</div>
