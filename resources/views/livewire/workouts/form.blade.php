@php($Type = \App\Enums\ExerciseType::class)

<div>
    <x-page-header :title="$workout ? 'Workout bewerken' : 'Nieuwe workout'"
                   description="Kies je oefeningen, zet ze in volgorde en vul per oefening je doel in." />


    <form wire:submit="save" class="flex flex-col gap-5">
        <x-card>
            <x-form.section title="Algemeen" description="Naam, kleur en (optioneel) hoe lang de workout duurt.">
                <x-form.input label="Naam" wire:model="name" required class="sm:col-span-2" placeholder="Bijv. Push, Benen + cardio" />

                <fieldset class="sm:col-span-2">
                    <legend class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Kleur</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Models\Workout::COLORS as $hex)
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="color" value="{{ $hex }}" class="peer sr-only">
                                <span style="background-color: {{ $hex }}"
                                      class="block size-9 rounded-full ring-2 ring-transparent ring-offset-2 ring-offset-white transition peer-checked:ring-ink peer-focus-visible:ring-accent-400 dark:ring-offset-panel-dark dark:peer-checked:ring-white"></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <x-form.input label="Duur" type="number" wire:model="estimated_minutes" inputmode="numeric" suffix="min"
                              placeholder="Automatisch" hint="Leeg laten = geschat uit sets, rust en cardio-tijd" />
                <x-form.textarea label="Omschrijving" wire:model="description" rows="2" class="sm:col-span-2" />
            </x-form.section>
        </x-card>

        <x-card title="Oefeningen" description="Sleep aan het handvat om de volgorde te wijzigen. Het doel komt van de oefening; pas het hier aan voor deze workout.">
            <x-slot:actions>
                <x-button type="button" variant="secondary" size="sm" icon="plus" wire:click="openPicker" class="shrink-0">Toevoegen</x-button>
            </x-slot:actions>

            @error('items')
                <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
            @enderror

            <ol wire:sort="sortItem" class="flex flex-col gap-3">
                @foreach ($items as $i => $item)
                    @php($exercise = $this->itemExercises[$item['exercise_id']] ?? null)
                    @continue(! $exercise)
                    @php($type = $exercise->type)
                    @php($primary = $exercise->primaryMuscleGroup())
                    <li wire:key="item-{{ $item['key'] }}" wire:sort:item="{{ $item['key'] }}" style="--g: {{ $primary?->color ?? '#1d63c5' }}"
                        class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200 sm:p-4 dark:bg-white/[.03] dark:ring-line-dark">
                        <div class="flex items-center gap-3">
                            <button type="button" wire:sort:handle aria-label="Verslepen"
                                    class="grid h-10 w-7 shrink-0 cursor-grab touch-none place-items-center rounded-lg text-slate-400 hover:bg-slate-200/60 hover:text-ink active:cursor-grabbing dark:hover:bg-white/10 dark:hover:text-white">
                                <x-icon name="grip" class="size-5" />
                            </button>
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-(--c,var(--color-brand-700)) text-sm font-bold text-white" style="--c: {{ $color }}">{{ $loop->iteration }}</span>
                            <div class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-white dark:bg-white/5">
                                @if ($picture = $exercise->imageUrl())
                                    <img src="{{ $picture }}" alt="" class="size-full object-contain p-0.5">
                                @else
                                    <x-icon :name="$type->icon()" class="size-5 text-(--g)" />
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">{{ $exercise->name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $type->label() }}@if ($primary) · {{ $primary->name }}@endif</p>
                            </div>
                            <button type="button" wire:click="removeItem('{{ $item['key'] }}')" aria-label="Weghalen"
                                    class="grid size-9 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400">
                                <x-icon name="x" class="size-5" />
                            </button>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @if ($type->usesSets())
                                <x-form.input label="Sets" type="number" wire:model="items.{{ $i }}.sets" inputmode="numeric" min="1" />
                            @endif
                            @if ($type->usesReps())
                                <x-form.input label="Herhalingen" type="number" wire:model="items.{{ $i }}.reps" inputmode="numeric" min="1" />
                            @endif
                            @if ($type->usesWeight())
                                <x-form.input label="Gewicht" wire:model="items.{{ $i }}.weight" inputmode="decimal" suffix="kg" />
                            @endif
                            @if ($type->usesExtraWeight())
                                <x-form.input label="Extra gewicht" wire:model="items.{{ $i }}.weight" inputmode="decimal" suffix="kg" placeholder="0" />
                            @endif
                            @if ($type === $Type::Duration)
                                <x-form.input label="Tijd per set" type="number" wire:model="items.{{ $i }}.seconds" inputmode="numeric" suffix="sec" />
                            @endif
                            @if ($type->usesSets())
                                <x-form.input label="Rust" type="number" wire:model="items.{{ $i }}.rest" inputmode="numeric" suffix="sec" />
                            @endif
                            @if ($type === $Type::Cardio)
                                <x-form.input label="Tijd" type="number" wire:model="items.{{ $i }}.minutes" inputmode="numeric" suffix="min" />
                                @php($metric = \App\Enums\CardioMetric::tryFrom((string) $item['metric']))
                                <x-form.select label="Instelling" wire:model.live="items.{{ $i }}.metric" placeholder="Geen"
                                               :options="\App\Enums\CardioMetric::options()" />
                                @if ($metric)
                                    <x-form.input :label="$metric->label()" wire:model="items.{{ $i }}.metric_value" inputmode="decimal" :suffix="$metric->unit()" />
                                @endif
                            @endif
                            <x-form.input label="Notitie" wire:model="items.{{ $i }}.notes" placeholder="Bijv. stoel op stand 4" class="col-span-2 sm:col-span-4" />
                        </div>
                    </li>
                @endforeach
            </ol>

            <button type="button" wire:click="openPicker"
                    @class(['flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 py-4 font-semibold text-slate-500 transition hover:border-brand-400 hover:text-brand-700 dark:border-line-dark dark:text-slate-400 dark:hover:border-brand-400 dark:hover:text-brand-300',
                            'mt-3' => $items])>
                <x-icon name="plus" class="size-5" /> Oefening toevoegen
            </button>
        </x-card>

        <x-card title="Spiergroepen" description="Wat train je met deze workout? Leeg laten = automatisch de primaire spiergroepen van je oefeningen.">
            @if ($items)
                <x-slot:actions>
                    <x-button type="button" variant="ghost" size="sm" wire:click="fillMuscleGroups" class="shrink-0">Overnemen</x-button>
                </x-slot:actions>
            @endif
            <x-form.chips wire:model="muscle_group_ids"
                          :options="$this->muscleGroups->map(fn ($g) => ['value' => $g->id, 'label' => $g->name, 'color' => $g->color])" />
        </x-card>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <x-button variant="ghost" :href="$workout ? route('workouts.show', $workout) : route('workouts.index')" wire:navigate>Annuleren</x-button>
            <x-button icon="check" loading="save">Opslaan</x-button>
        </div>
    </form>

    {{-- Oefening kiezen --}}
    <x-modal wire:model="showPicker" title="Oefening toevoegen" maxWidth="2xl">
        <div class="flex flex-col gap-3">
            <div class="grid gap-3 sm:grid-cols-[1fr_13rem]">
                <x-form.input wire:model.live.debounce.250ms="pickerSearch" icon="search" placeholder="Zoek een oefening…" aria-label="Zoeken" />
                <x-form.select wire:model.live="pickerGroup" :options="$this->muscleGroups->pluck('name', 'id')" placeholder="Alle spiergroepen" aria-label="Spiergroep" />
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach (['' => 'Alles'] + collect($Type::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all() as $value => $label)
                    <button type="button" wire:click="$set('pickerType', '{{ $value }}')" wire:key="pt-{{ $value ?: 'all' }}"
                            @class(['rounded-full px-3.5 py-1.5 text-sm font-semibold ring-1 ring-inset transition',
                                    'bg-brand-700 text-white ring-brand-700 dark:bg-brand-500 dark:ring-brand-500' => $pickerType === $value,
                                    'bg-white ring-slate-300 hover:bg-slate-50 dark:bg-white/5 dark:ring-line-dark' => $pickerType !== $value])>{{ $label }}</button>
                @endforeach
            </div>

            @php($added = collect($items)->countBy('exercise_id'))
            <ul class="-mx-2 flex max-h-[50dvh] flex-col overflow-y-auto">
                @forelse ($this->pickerExercises as $option)
                    @php($primary = $option->primaryMuscleGroup())
                    <li wire:key="opt-{{ $option->id }}">
                        <button type="button" wire:click="addExercise({{ $option->id }})" style="--g: {{ $primary?->color ?? '#1d63c5' }}"
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

        <x-slot:footer>
            <span class="mr-auto self-center text-sm text-slate-500 dark:text-slate-400">{{ count($items) }} in deze workout</span>
            <x-button type="button" x-on:click="show = false">Klaar</x-button>
        </x-slot:footer>
    </x-modal>
</div>
