{{--
    Oefening kiezen (hoort bij de trait PicksExercises), in een <x-modal>:
    <x-exercise-picker :exercises="$this->pickerExercises" :groups="$this->muscleGroups" :type="$pickerType"
                       :own="$pickerOwn" :own-count="$this->pickerOwnCount"
                       :added="collect($items)->countBy('exercise_id')" action="addExercise" />
--}}
@props(['exercises', 'groups', 'type' => '', 'own' => false, 'ownCount' => 0, 'added' => [], 'action'])

<div class="flex flex-col gap-3">
    <div class="grid gap-3 sm:grid-cols-[1fr_13rem]">
        <x-form.input wire:model.live.debounce.250ms="pickerSearch" icon="search" placeholder="Zoek een oefening…" aria-label="Zoeken" />
        <x-form.select wire:model.live="pickerGroup" :options="$groups->pluck('name', 'id')" placeholder="Alle spiergroepen" aria-label="Spiergroep" />
    </div>
    <div class="flex flex-wrap gap-2">
        @foreach (['' => 'Alles'] + collect(\App\Enums\ExerciseType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all() as $value => $label)
            <button type="button" wire:click="$set('pickerType', '{{ $value }}')" wire:key="pt-{{ $value ?: 'all' }}"
                    @class(['rounded-full px-3.5 py-1.5 text-sm font-semibold ring-1 ring-inset transition',
                            'bg-brand-700 text-white ring-brand-700 dark:bg-brand-500 dark:ring-brand-500' => $type === $value,
                            'bg-white ring-slate-300 hover:bg-slate-50 dark:bg-white/5 dark:ring-line-dark' => $type !== $value])>{{ $label }}</button>
        @endforeach
        @if ($ownCount)
            <button type="button" wire:click="$toggle('pickerOwn')" aria-pressed="{{ $own ? 'true' : 'false' }}"
                    @class(['ml-auto inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-semibold ring-1 ring-inset transition',
                            'bg-brand-700 text-white ring-brand-700 dark:bg-brand-500 dark:ring-brand-500' => $own,
                            'bg-white ring-slate-300 hover:bg-slate-50 dark:bg-white/5 dark:ring-line-dark' => ! $own])>
                <x-icon name="user" class="size-4" /> Eigen oefeningen
            </button>
        @endif
    </div>

    <ul class="-mx-2 flex max-h-[50dvh] flex-col overflow-y-auto">
        @forelse ($exercises as $option)
            @php($primary = $option->primaryMuscleGroup())
            <li wire:key="opt-{{ $option->id }}">
                <button type="button" wire:click="{{ $action }}({{ $option->id }})" style="--g: {{ $primary?->color ?? '#1d63c5' }}"
                        class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left hover:bg-slate-50 dark:hover:bg-white/5">
                    <span class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-50 dark:bg-white/5">
                        @if ($picture = $option->imageUrl())
                            <img src="{{ $picture }}" alt="" loading="lazy" class="size-full object-contain p-0.5">
                        @else
                            <x-icon :name="$option->type->icon()" class="size-5 text-(--g)" />
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold">{{ $option->name }}</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $option->type->label() }}@if ($primary) · {{ $primary->name }}@endif @unless ($option->isStandard()) · Eigen @endunless</span>
                    </span>
                    @if ($count = $added[$option->id] ?? 0)
                        <x-badge color="success" icon="check">{{ $count > 1 ? $count.'×' : 'Toegevoegd' }}</x-badge>
                    @else
                        <x-icon name="plus" class="size-5 text-brand-700 dark:text-brand-300" />
                    @endif
                </button>
            </li>
        @empty
            <li class="px-2 py-6 text-center text-slate-500 dark:text-slate-400">Geen oefeningen gevonden.</li>
        @endforelse
    </ul>
</div>
