<div>
    <x-page-header :title="$exercise ? 'Oefening bewerken' : 'Nieuwe oefening'"
                   description="Type, spiergroepen, plaatje en een standaarddoel" />

    @if (session('status'))
        <x-alert type="success" class="mb-5">{{ session('status') }}</x-alert>
    @endif

    <form wire:submit="save">
        <x-card>
            <x-form.section title="Algemeen" description="Naam, type en waarmee je de oefening doet.">
                <x-form.input label="Naam" wire:model="name" required class="sm:col-span-2" placeholder="Bijv. Chest Press Machine" />

                <x-form.radio-cards label="Type" wire:model.live="type" class="sm:col-span-2" :options="\App\Enums\ExerciseType::options()" />

                <x-form.select label="Materiaal" wire:model="equipment" :options="\App\Enums\Equipment::options()" required />
            </x-form.section>

            <x-form.section title="Spiergroepen" description="Eén primaire spiergroep; die telt mee in je weektotaal. Secundair is optioneel." :columns="1">
                <x-form.select label="Primaire spiergroep" wire:model.live="primary_muscle_group_id" placeholder="Kies een spiergroep" required>
                    @foreach ($this->muscleGroups->groupBy(fn ($g) => $g->bodyPart->name) as $bodyPart => $groups)
                        <optgroup label="{{ $bodyPart }}">
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-form.select>

                <x-form.chips label="Secundaire spiergroepen" wire:model="secondary_muscle_group_ids" hint="Spieren die meehelpen"
                              :options="$this->muscleGroups->reject(fn ($g) => $g->id === (int) $primary_muscle_group_id)
                                  ->map(fn ($g) => ['value' => $g->id, 'label' => $g->name, 'color' => $g->color])" />
            </x-form.section>

            <x-form.section title="Plaatje" description="Zonder eigen plaatje zie je dat van de primaire spiergroep." :columns="1">
                <x-form.image-upload wire:model="image" remove="deleteImage" hint="JPG, PNG of WEBP · max 2 MB"
                                     :preview="$image?->temporaryUrl() ?? $currentImage" />
            </x-form.section>

            <x-form.section title="Standaarddoel" description="Wordt voorgevuld als je de oefening aan een workout toevoegt." :columns="3">
                @if ($exerciseType->usesSets())
                    <x-form.input label="Sets" type="number" wire:model="default_sets" inputmode="numeric" min="1" />
                @endif
                @if ($exerciseType->usesReps())
                    <x-form.input label="Herhalingen" type="number" wire:model="default_reps" inputmode="numeric" min="1" />
                @endif
                @if ($exerciseType->usesWeight())
                    <x-form.input label="Gewicht" wire:model="default_weight" inputmode="decimal" suffix="kg" />
                @endif
                @if ($exerciseType->usesExtraWeight())
                    <x-form.input label="Extra gewicht" wire:model="default_weight" inputmode="decimal" suffix="kg"
                                  hint="Leeg = alleen je eigen gewicht. Negatief bij assisted, bv. -20." />
                @endif
                @if ($exerciseType === \App\Enums\ExerciseType::Duration)
                    <x-form.input label="Tijd per set" type="number" wire:model="default_seconds" inputmode="numeric" suffix="sec" />
                @endif
                @if ($exerciseType === \App\Enums\ExerciseType::Cardio)
                    <x-form.input label="Tijd" type="number" wire:model="default_minutes" inputmode="numeric" suffix="min" />
                    <x-form.select label="Instelling" wire:model.live="default_metric" placeholder="Geen"
                                   :options="\App\Enums\CardioMetric::options()" />
                    @if ($default_metric)
                        <x-form.input label="{{ \App\Enums\CardioMetric::from($default_metric)->label() }}" wire:model="default_metric_value"
                                      inputmode="decimal" :suffix="\App\Enums\CardioMetric::from($default_metric)->unit()" />
                    @endif
                @endif
            </x-form.section>

            <x-form.section title="Uitleg" description="Optioneel. Bij uitvoering en tips: één stap of tip per regel." :columns="1">
                <x-form.textarea label="Omschrijving" wire:model="description" rows="2" />
                <x-form.textarea label="Uitvoering" wire:model="instructions" rows="5" hint="Eén stap per regel" />
                <x-form.textarea label="Tips" wire:model="tips" rows="3" hint="Eén tip per regel" />
            </x-form.section>

            <x-slot:footer>
                <x-button variant="ghost" :href="$exercise ? route('exercises.show', $exercise) : route('exercises.index')" wire:navigate>Annuleren</x-button>
                <x-button icon="check" loading="save">Opslaan</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
