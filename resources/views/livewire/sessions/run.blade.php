@php
    $Type = \App\Enums\ExerciseType::class;
    $input = 'w-full min-w-0 rounded-lg border-0 bg-white px-2 py-2 text-center font-semibold tabular-nums ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-line-dark';
@endphp

{{-- Ruimte onderaan voor de timerbalk --}}
<div x-on:exercise-added.window="$nextTick(() => document.getElementById(`se-${$event.detail.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
     class="pb-20 md:pb-24" style="--c: {{ $session->color ?? '#1d63c5' }}">
    <x-page-header :title="$session->name"
                   :description="($session->isQuickstart() ? \App\Models\WorkoutSession::QUICKSTART_DESCRIPTION.' ' : '').'Gestart om '.$session->started_at->format('H:i').'.'">
        <x-button variant="ghost" size="sm" type="button" wire:click="$set('confirmingCancel', true)">Stoppen zonder opslaan</x-button>
    </x-page-header>

    {{-- Verslepen via het grip-icoon, of "Later" om een oefening achteraan te zetten als de machine bezet is --}}
    <div wire:sort="sortExercise" class="flex flex-col gap-4">
        @foreach ($session->exercises as $item)
            @php($exercise = $item->exercise)
            @php($type = $exercise->type)
            @php($primary = $exercise->primaryMuscleGroup())
            @php($done = $item->isDone())
            <section id="se-{{ $item->id }}" wire:key="se-{{ $item->id }}" wire:sort:item="{{ $item->id }}" style="--g: {{ $primary?->color ?? '#1d63c5' }}"
                     @class(['rounded-2xl bg-white shadow-sm ring-1 transition dark:bg-panel-dark',
                             'ring-emerald-300 dark:ring-emerald-500/40' => $done,
                             'ring-slate-200 dark:ring-line-dark' => ! $done])>
                <header class="flex items-center gap-3 p-3 pl-1.5 sm:p-4 sm:pl-2">
                    <button type="button" wire:sort:handle aria-label="Verslepen"
                            class="-mr-1.5 grid h-10 w-7 shrink-0 cursor-grab touch-none place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-ink active:cursor-grabbing dark:hover:bg-white/10 dark:hover:text-white">
                        <x-icon name="grip" class="size-5" />
                    </button>
                    <span @class(['grid size-7 shrink-0 place-items-center rounded-full text-sm font-bold text-white',
                                  'bg-emerald-500' => $done, 'bg-(--c)' => ! $done])>
                        @if ($done) <x-icon name="check" class="size-4" /> @else {{ $loop->iteration }} @endif
                    </span>
                    <div class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-50 dark:bg-white/5">
                        @if ($picture = $exercise->imageUrl())
                            <img src="{{ $picture }}" alt="" class="size-full object-contain p-0.5">
                        @else
                            <x-icon :name="$type->icon()" class="size-5 text-(--g)" />
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate font-semibold">{{ $exercise->name }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Doel: {{ $item->targetSummary() ?? '–' }}@if ($item->rest_seconds && $type->usesSets()) · rust {{ \App\Models\WorkoutExercise::duration($item->rest_seconds) }}@endif
                        </p>
                        @if ($item->notes)
                            <p class="text-sm text-slate-500 italic dark:text-slate-400">{{ $item->notes }}</p>
                        @endif
                    </div>
                    @unless ($done || $loop->last)
                        <button type="button" wire:click="moveToEnd({{ $item->id }})" title="Machine bezet? Zet deze oefening achteraan"
                                class="flex shrink-0 items-center gap-1 self-start rounded-lg px-2 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-ink dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white">
                            Later <x-icon name="chevron-down" class="size-3.5" />
                        </button>
                    @endunless
                </header>

                @if ($type->usesSets())
                    {{-- Sets --}}
                    <div class="max-w-xl px-3 pb-3 sm:px-4 sm:pb-4">
                        <div @class(['grid items-center gap-2 px-1 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400',
                                     'grid-cols-[2.5rem_1fr_1fr_3rem]' => $type->usesReps(),
                                     'grid-cols-[2.5rem_1fr_3rem]' => ! $type->usesReps()])>
                            <span>Set</span>
                            @if ($type->usesReps())
                                <span class="text-center">{{ $type->usesExtraWeight() ? 'Extra kg' : 'kg' }}</span>
                                <span class="text-center">Reps</span>
                            @else
                                <span class="text-center">Seconden</span>
                            @endif
                            <span></span>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            @foreach ($item->sets as $set)
                                <div wire:key="set-{{ $set->id }}"
                                     @class(['grid items-center gap-2 rounded-xl p-1 transition',
                                             'grid-cols-[2.5rem_1fr_1fr_3rem]' => $type->usesReps(),
                                             'grid-cols-[2.5rem_1fr_3rem]' => ! $type->usesReps(),
                                             'bg-emerald-50 dark:bg-emerald-500/10' => $set->is_completed])>
                                    <span class="text-center font-display text-lg font-bold text-slate-500 dark:text-slate-400">{{ $set->set_number }}</span>
                                    @if ($type->usesReps())
                                        <input type="text" inputmode="decimal" wire:model.blur.live="values.{{ $set->id }}.weight" aria-label="Gewicht set {{ $set->set_number }}"
                                               placeholder="{{ $type->usesExtraWeight() ? '0' : '–' }}" @class([$input, 'ring-red-500!' => $errors->has("values.{$set->id}.weight")])>
                                        <input type="number" inputmode="numeric" wire:model.blur.live="values.{{ $set->id }}.reps" aria-label="Herhalingen set {{ $set->set_number }}"
                                               placeholder="{{ $set->target_reps ?? '–' }}" @class([$input, 'ring-red-500!' => $errors->has("values.{$set->id}.reps")])>
                                    @else
                                        <input type="number" inputmode="numeric" wire:model.blur.live="values.{{ $set->id }}.seconds" aria-label="Seconden set {{ $set->set_number }}"
                                               placeholder="{{ $set->target_duration_seconds ?? '–' }}" @class([$input, 'ring-red-500!' => $errors->has("values.{$set->id}.seconds")])>
                                    @endif
                                    <button type="button" wire:click="toggleSet({{ $set->id }})" aria-label="Set {{ $set->set_number }} {{ $set->is_completed ? 'niet ' : '' }}gedaan"
                                            @class(['grid h-10 place-items-center rounded-xl transition',
                                                    'bg-emerald-500 text-white hover:bg-emerald-600' => $set->is_completed,
                                                    'bg-slate-100 text-slate-400 hover:bg-slate-200 hover:text-ink dark:bg-white/10 dark:hover:bg-white/15 dark:hover:text-white' => ! $set->is_completed])>
                                        <x-icon name="check" class="size-5" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex gap-2">
                            <x-button variant="ghost" size="sm" type="button" icon="plus" wire:click="addSet({{ $item->id }})">Set</x-button>
                            @if ($item->sets->count() > 1)
                                <x-button variant="ghost" size="sm" type="button" icon="minus" wire:click="removeSet({{ $item->id }})">Set</x-button>
                            @endif
                        </div>
                    </div>
                @else
                    {{-- Cardio: gegevens van het apparaat --}}
                    @php($metric = \App\Enums\CardioMetric::tryFrom((string) ($cardio[$item->id]['metric'] ?? '')))
                    <div class="px-3 pb-3 sm:px-4 sm:pb-4">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <x-form.input label="Tijd" wire:model.blur.live="cardio.{{ $item->id }}.time" placeholder="mm:ss" inputmode="numeric" hint="Bijv. 20 of 20:30" />
                            <x-form.input label="Afstand" wire:model.blur.live="cardio.{{ $item->id }}.distance" inputmode="decimal" suffix="km" />
                            <x-form.select label="Instelling" wire:model.live="cardio.{{ $item->id }}.metric" placeholder="Geen" :options="\App\Enums\CardioMetric::options()" />
                            @if ($metric)
                                <x-form.input :label="$metric->label()" wire:model.blur.live="cardio.{{ $item->id }}.metric_value" inputmode="decimal" :suffix="$metric->unit()" />
                            @else
                                <div></div>
                            @endif
                            {{-- Overal nodig: watts/METs en ACT/TTL --}}
                            @foreach (['avg_watts', 'mets', 'active_kcal', 'total_kcal'] as $field)
                                <x-form.input :label="\App\Models\CardioLog::FIELDS[$field][0]" wire:model.blur.live="cardio.{{ $item->id }}.{{ $field }}" inputmode="decimal" :suffix="\App\Models\CardioLog::FIELDS[$field][1]" />
                            @endforeach
                            {{-- Daarna de velden van dit apparaat (in te stellen bij de oefening) en AVG ACT/HR --}}
                            @foreach ($exercise->cardioFields() as $extra)
                                @continue($extra === \App\Enums\CardioField::Splits)
                                <x-form.input :label="$extra->label()" wire:model.blur.live="cardio.{{ $item->id }}.{{ $extra->value }}"
                                              :inputmode="$extra === \App\Enums\CardioField::Incline ? 'decimal' : 'numeric'"
                                              :suffix="$extra->unit()" :placeholder="$extra === \App\Enums\CardioField::Pace ? 'mm:ss' : null" />
                            @endforeach
                            <x-form.input :label="\App\Models\CardioLog::FIELDS['avg_act_hr'][0]" wire:model.blur.live="cardio.{{ $item->id }}.avg_act_hr" inputmode="numeric" :suffix="\App\Models\CardioLog::FIELDS['avg_act_hr'][1]" />
                            <x-form.input :label="\App\Models\CardioLog::FIELDS['avg_heart_rate'][0]" wire:model.blur.live="cardio.{{ $item->id }}.avg_heart_rate" inputmode="numeric" :suffix="\App\Models\CardioLog::FIELDS['avg_heart_rate'][1]" />
                        </div>
                        {{-- Tussentijden: na elke km op "Volgende km" en de tijd van die km invullen --}}
                        @php($times = $splits[$item->id] ?? [])
                        @php($kmTimes = array_filter($item->cardio?->kmTimes() ?? []))
                        @if ($times || $exercise->hasCardioField(\App\Enums\CardioField::Splits))
                        <div x-data="{ open: {{ $times ? 'true' : 'false' }} }"
                             x-on:split-added.window="if ($event.detail.id === {{ $item->id }}) { open = true; $nextTick(() => [...$el.querySelectorAll('input')].pop()?.focus()) }"
                             class="mt-4 rounded-xl bg-slate-50 p-3 dark:bg-white/[.03]">
                            <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-2 text-left text-sm font-semibold">
                                <span>
                                    Tussentijden per km
                                    @if ($kmTimes)
                                        <span class="font-normal text-slate-500 dark:text-slate-400">· {{ count($kmTimes) }} km in {{ \App\Models\WorkoutSession::clock(array_sum($kmTimes)) }}</span>
                                    @endif
                                </span>
                                <x-icon name="chevron-down" class="size-4 transition" ::class="open && 'rotate-180'" />
                            </button>
                            <div x-show="open" x-cloak class="mt-3">
                                @if ($times)
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                                        @foreach ($times as $number => $time)
                                            <div wire:key="split-{{ $item->id }}-{{ $number }}">
                                                <div class="mb-0.5 flex items-center justify-between">
                                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Km {{ $number }}</span>
                                                    <button type="button" wire:click="removeSplit({{ $item->id }}, {{ $number }})" aria-label="Km {{ $number }} verwijderen"
                                                            class="rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-red-600 dark:hover:bg-white/10">
                                                        <x-icon name="x" class="size-3.5" />
                                                    </button>
                                                </div>
                                                <input type="text" inputmode="decimal" wire:model.blur.live="splits.{{ $item->id }}.{{ $number }}" placeholder="mm:ss"
                                                       aria-label="Tussentijd km {{ $number }}" @class([$input, 'ring-red-500!' => $errors->has("splits.{$item->id}.{$number}")])>
                                                <span class="mt-0.5 block h-4 text-center text-xs tabular-nums text-slate-500 dark:text-slate-400">
                                                    @if ($speed = \App\Models\CardioLog::speed($kmTimes[$number] ?? null)) {{ $speed }} km/h @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <x-button variant="secondary" size="sm" type="button" icon="plus" wire:click="addSplit({{ $item->id }})" @class(['mt-2' => $times])>
                                    {{ $times ? 'Volgende km' : 'Km 1' }}
                                </x-button>
                            </div>
                        </div>
                        @endif

                        <div class="mt-3 flex justify-end">
                            <x-button type="button" size="sm" :variant="$done ? 'secondary' : 'primary'" icon="check" wire:click="toggleCardio({{ $item->id }})">
                                {{ $done ? 'Gedaan' : 'Afronden' }}
                            </x-button>
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    {{-- Oefening toevoegen: bij een vrije training begin je hier, bij een workout kun je er eentje bij doen --}}
    @if ($session->exercises->isEmpty())
        <div class="rounded-2xl border-2 border-dashed border-slate-300 px-4 py-8 text-center dark:border-line-dark">
            <p class="font-semibold">Nog geen oefeningen</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Voeg je eerste oefening toe en vul je doel in.</p>
            <x-button type="button" icon="plus" wire:click="openAdding" class="mt-4">Oefening toevoegen</x-button>
        </div>
    @else
        <button type="button" wire:click="openAdding"
                class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 py-4 font-semibold text-slate-500 transition hover:border-brand-400 hover:text-brand-700 dark:border-line-dark dark:text-slate-400 dark:hover:border-brand-400 dark:hover:text-brand-300">
            <x-icon name="plus" class="size-5" /> Oefening toevoegen
        </button>
    @endif

    {{-- Kiezen, daarna het doel invullen (zoals bij een workout) --}}
    <x-modal wire:model="showPicker" :title="$adding ? 'Doel invullen' : 'Oefening toevoegen'" maxWidth="2xl">
        @if ($adding)
            @php($chosen = \App\Models\Exercise::find($adding['exercise_id']))
            <div class="mb-4 flex items-center gap-3">
                <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-50 dark:bg-white/5">
                    @if ($picture = $chosen->imageUrl())
                        <img src="{{ $picture }}" alt="" class="size-full object-contain p-0.5">
                    @else
                        <x-icon :name="$chosen->type->icon()" class="size-5" />
                    @endif
                </span>
                <div class="min-w-0">
                    <p class="truncate font-semibold">{{ $chosen->name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $chosen->type->label() }}</p>
                </div>
            </div>
            <x-exercise-targets :type="$chosen->type" :item="$adding" model="adding" />
        @else
            <x-exercise-picker :exercises="$this->pickerExercises" :groups="$this->muscleGroups" :type="$pickerType"
                               :added="$session->exercises->countBy('exercise_id')" action="pick" />
        @endif
        <x-slot:footer>
            @if ($adding)
                <x-button variant="ghost" type="button" wire:click="$set('adding', null)">Andere oefening</x-button>
                <x-button type="button" icon="plus" wire:click="addExercise" loading="addExercise">Toevoegen</x-button>
            @else
                <x-button variant="ghost" type="button" x-on:click="show = false">Annuleren</x-button>
            @endif
        </x-slot:footer>
    </x-modal>

    {{-- Timerbalk: op mobiel boven de menubalk, op grotere schermen onderaan het scherm --}}
    <div x-data="{
             offset: {{ now()->getTimestampMs() }} - Date.now(),
             now: Date.now(),
             restUntil: null,
             restTotal: 0,
             init() {
                 setInterval(() => {
                     this.now = Date.now();
                     if (this.restUntil && this.now >= this.restUntil) {
                         this.restUntil = null;
                         navigator.vibrate?.([200, 100, 200]);
                     }
                 }, 250);
             },
             get elapsed() {
                 const d = this.$root.dataset;
                 const end = d.pausedAt ? +d.pausedAt : this.now + this.offset;
                 return Math.max(0, Math.floor((end - +d.started) / 1000) - +d.pausedSeconds);
             },
             get rest() { return this.restUntil ? Math.max(0, Math.ceil((this.restUntil - this.now) / 1000)) : 0 },
             clock(s) {
                 const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), x = String(s % 60).padStart(2, '0');
                 return h ? `${h}:${String(m).padStart(2, '0')}:${x}` : `${String(m).padStart(2, '0')}:${x}`;
             },
         }"
         x-on:rest-start.window="restTotal = $event.detail.seconds; restUntil = Date.now() + $event.detail.seconds * 1000"
         data-started="{{ $session->started_at->getTimestampMs() }}"
         data-paused-seconds="{{ $session->paused_seconds }}"
         data-paused-at="{{ $session->paused_at?->getTimestampMs() }}"
         class="fixed inset-x-0 bottom-[calc(env(safe-area-inset-bottom)+4.75rem)] z-20 px-2 md:bottom-4 md:left-20 md:px-7 lg:left-64 lg:px-10">
        <div class="mx-auto flex max-w-6xl items-center gap-3 rounded-2xl bg-brand-700 p-2 pl-4 text-white shadow-xl shadow-brand-950/30 ring-1 ring-white/10 dark:bg-brand-900">
            {{-- Totale tijd --}}
            <div class="min-w-0">
                <p class="font-display text-2xl leading-none font-extrabold tabular-nums sm:text-[28px]" x-text="clock(elapsed)">{{ \App\Models\WorkoutSession::clock($session->elapsedSeconds()) }}</p>
                <p class="text-xs text-brand-200">
                    @if ($session->isPaused()) Gepauzeerd @else {{ $setsDone }}/{{ $setsTotal }} sets @endif
                </p>
            </div>

            {{-- Rusttimer --}}
            <button type="button" x-show="rest > 0" x-cloak x-on:click="restUntil = null" title="Rust overslaan" aria-label="Rust overslaan"
                    class="relative mx-auto shrink-0 overflow-hidden rounded-xl bg-accent-400 px-3 pt-1 pb-1.5 text-center text-brand-950">
                <span class="block text-[10px] font-bold uppercase tracking-wider">Rust</span>
                <span class="block font-display text-xl leading-none font-bold tabular-nums" x-text="clock(rest)"></span>
                <span class="absolute bottom-0 left-0 h-1 bg-brand-950/30 transition-[width] duration-300" :style="`width: ${restTotal ? rest / restTotal * 100 : 0}%`"></span>
            </button>

            <div class="ml-auto flex shrink-0 items-center gap-2" :class="rest > 0 && 'ml-0!'">
                @if ($session->isPaused())
                    <button type="button" wire:click="resume" aria-label="Hervatten" class="grid size-11 place-items-center rounded-xl bg-white/15 hover:bg-white/25">
                        <x-icon name="play" />
                    </button>
                @else
                    <button type="button" wire:click="pause" aria-label="Pauze" class="grid size-11 place-items-center rounded-xl bg-white/15 hover:bg-white/25">
                        <x-icon name="pause" />
                    </button>
                @endif
                <x-button variant="fire" type="button" wire:click="$set('confirmingFinish', true)" class="h-11 px-3! sm:px-4!">Afronden</x-button>
            </div>
        </div>
    </div>

    <x-modal wire:model="confirmingFinish" title="Training afronden?" maxWidth="md">
        <p>
            @if ($setsDone < $setsTotal)
                Je hebt <strong>{{ $setsDone }} van de {{ $setsTotal }}</strong> sets afgevinkt. Wat niet is afgevinkt, telt niet mee.
            @else
                Goed bezig! Alle sets zijn afgevinkt.
            @endif
        </p>
        <x-slot:footer>
            <x-button variant="ghost" type="button" x-on:click="show = false">Verder trainen</x-button>
            <x-button variant="fire" type="button" icon="check" wire:click="finish" loading="finish">Afronden</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="confirmingCancel" title="Stoppen zonder opslaan?" maxWidth="md">
        <p>De training en alles wat je hebt ingevuld worden verwijderd. Dit kan niet ongedaan worden gemaakt.</p>
        <x-slot:footer>
            <x-button variant="ghost" type="button" x-on:click="show = false">Verder trainen</x-button>
            <x-button variant="danger" type="button" wire:click="cancel" loading="cancel">Stoppen</x-button>
        </x-slot:footer>
    </x-modal>
</div>
