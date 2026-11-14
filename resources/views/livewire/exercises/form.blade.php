<div>
    <x-page-header :title="$exercise ? __('Edit exercise') : __('New exercise')"
                   :description="__('Type, muscle groups, image and a default target')" />


    <form wire:submit="save">
        <x-card>
            <x-form.section :title="__('General')" :description="__('Name, type and what you do the exercise with.')">
                <x-form.input :label="__('Name')" wire:model="name" required class="sm:col-span-2" :placeholder="__('e.g. :example', ['example' => 'Chest Press Machine'])" />

                @if ($typeLocked)
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Type') }}</span>
                        <div class="flex items-center gap-3 rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-200 dark:bg-brand-500/15 dark:ring-brand-400/40">
                            <x-icon :name="$exerciseType->icon()" class="size-6 shrink-0 text-brand-700 dark:text-brand-300" />
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $exerciseType->label() }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $exerciseType->description() }}</p>
                            </div>
                            <x-icon name="lock" class="ms-auto size-5 shrink-0 text-slate-400" />
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ __('The type is fixed, because this exercise is already in a workout or training. Want another type? Create a new exercise and archive this one.') }}
                        </p>
                    </div>
                @else
                    <x-form.radio-cards :label="__('Type')" wire:model.live="type" class="sm:col-span-2" :options="\App\Enums\ExerciseType::options()" />
                @endif

                <x-form.select :label="__('Equipment')" wire:model="equipment" :options="\App\Enums\Equipment::options()" required />
            </x-form.section>

            <x-form.section :title="__('Muscle groups')" :description="__('One primary muscle group; it counts towards your weekly total. Secondary is optional.')" :columns="1">
                <x-form.select :label="__('Primary muscle group')" wire:model.live="primary_muscle_group_id" :placeholder="__('Choose a muscle group')" required>
                    @foreach ($this->muscleGroups->groupBy(fn ($g) => $g->bodyPart->name) as $bodyPart => $groups)
                        <optgroup label="{{ $bodyPart }}">
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-form.select>

                <x-form.chips :label="__('Secondary muscle groups')" wire:model="secondary_muscle_group_ids" :hint="__('Muscles that help')"
                              :options="$this->muscleGroups->reject(fn ($g) => $g->id === (int) $primary_muscle_group_id)
                                  ->map(fn ($g) => ['value' => $g->id, 'label' => $g->name, 'color' => $g->color])" />
            </x-form.section>

            <x-form.section :title="__('Image')" :description="__('Without your own image you see the one of the primary muscle group.')" :columns="1">
                <x-form.image-upload wire:model="image" remove="deleteImage" :hint="__('JPG, PNG or WEBP · up to :size MB', ['size' => 2])"
                                     :preview="$image?->temporaryUrl() ?? $currentImage" />
            </x-form.section>

            <x-form.section :title="__('Default target')" :description="__('Prefilled when you add the exercise to a workout.')" :columns="3">
                @if ($exerciseType->usesSets())
                    <x-form.input :label="__('Sets')" type="number" wire:model="default_sets" inputmode="numeric" min="1" />
                @endif
                @if ($exerciseType->usesReps())
                    <x-form.input :label="__('Reps')" type="number" wire:model="default_reps" inputmode="numeric" min="1" />
                @endif
                @if ($exerciseType->usesWeight())
                    <x-form.input :label="__('Weight')" wire:model="default_weight" inputmode="decimal" suffix="kg" />
                @endif
                @if ($exerciseType->usesExtraWeight())
                    <x-form.input :label="__('Extra weight')" wire:model="default_weight" inputmode="decimal" suffix="kg"
                                  :hint="__('Empty = only your own weight. Negative for assisted, e.g. -20.')" />
                @endif
                @if ($exerciseType === \App\Enums\ExerciseType::Duration)
                    <x-form.input :label="__('Time per set')" type="number" wire:model="default_seconds" inputmode="numeric" suffix="sec" />
                @endif
                @if ($exerciseType === \App\Enums\ExerciseType::Cardio)
                    <x-form.input :label="__('Time')" type="number" wire:model="default_minutes" inputmode="numeric" suffix="min" />
                    <x-form.select :label="__('Setting')" wire:model.live="default_metric" :placeholder="__('None')"
                                   :options="\App\Enums\CardioMetric::options()" />
                    @if ($default_metric)
                        <x-form.input label="{{ \App\Enums\CardioMetric::from($default_metric)->label() }}" wire:model="default_metric_value"
                                      inputmode="decimal" :suffix="\App\Enums\CardioMetric::from($default_metric)->unit()" />
                    @endif
                    <div class="sm:col-span-full">
                        <x-form.chips :label="__('Extra fields on the machine')" wire:model="cardio_fields" :options="\App\Enums\CardioField::options()"
                                      :hint="__('You fill these in during the training, besides time, distance, kcal and heart rate.')" />
                    </div>
                @endif
            </x-form.section>

            <x-form.section :title="__('Explanation')" :description="__('Optional. For instructions and tips: one step or tip per line.')" :columns="1">
                <x-form.textarea :label="__('Description')" wire:model="description" rows="2" />
                <x-form.textarea :label="__('Instructions')" wire:model="instructions" rows="5" :hint="__('One step per line')" />
                <x-form.textarea :label="__('Tips')" wire:model="tips" rows="3" :hint="__('One tip per line')" />
            </x-form.section>

            <x-slot:footer>
                <x-button variant="ghost" :href="$exercise ? route('exercises.show', $exercise) : route('exercises.index')" wire:navigate>{{ __('Cancel') }}</x-button>
                <x-button icon="check" loading="save">{{ __('Save') }}</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
