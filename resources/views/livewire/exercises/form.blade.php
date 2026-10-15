<div>
    <x-page-header :title="$exercise ? 'Oefening bewerken' : 'Nieuwe oefening'"
                   description="Kracht of cardio, met afbeelding en spiergroepen"/>

    <form wire:submit="save">
        <x-card>
            <x-form.section title="Algemeen" description="Naam, type en hoe je de oefening uitvoert.">
                <x-form.input label="Naam" wire:model="name" required class="sm:col-span-2"
                              placeholder="Bijv. Bankdrukken"/>

                <x-form.radio-cards label="Type" wire:model.live="type" class="sm:col-span-2" :options="[
                    'strength' => ['label' => 'Kracht', 'icon' => 'dumbbell', 'description' => 'Sets, herhalingen en gewicht'],
                    'cardio' => ['label' => 'Cardio', 'icon' => 'run', 'description' => 'Tijd, afstand en intensiteit'],
                ]"/>

                <x-form.textarea label="Omschrijving" wire:model="description" rows="3" class="sm:col-span-2"
                                 hint="Uitvoering, aandachtspunten"/>
                <x-form.input label="Apparaat" wire:model="equipment" placeholder="Bijv. Crosstrainer, Dumbbell"/>
            </x-form.section>

            <x-form.section title="Afbeelding" :columns="1">
                <x-form.image-upload wire:model="image" remove="removeImage"
                                     :preview="$image?->temporaryUrl() ?? ($exercise?->image_path ? Storage::url($exercise->image_path) : null)"/>
            </x-form.section>

            @if ($type === 'strength')
                <x-form.section title="Spiergroepen" :columns="1">
                    <x-form.chips wire:model="muscle_group_ids" hint="Kies alles wat deze oefening traint"
                                  :options="$muscleGroups->map(fn ($g) => ['value' => $g->id, 'label' => $g->name, 'color' => $g->color])"/>
                </x-form.section>

                <x-form.section title="Standaarddoel"
                                description="Wordt voorgevuld als je de oefening aan een workout toevoegt."
                                :columns="3">
                    <x-form.input label="Sets" type="number" wire:model="default_sets" inputmode="numeric" min="1"/>
                    <x-form.input label="Herhalingen" type="number" wire:model="default_reps" inputmode="numeric"
                                  min="1"/>
                    <x-form.input label="Gewicht" wire:model="default_weight" inputmode="decimal" suffix="kg"/>
                </x-form.section>
            @else
                <x-form.section title="Standaarddoel" description="Meestal tijd en intensiteit." :columns="2">
                    <x-form.input label="Tijd" type="number" wire:model="default_minutes" inputmode="numeric"
                                  suffix="min"/>
                    <x-form.select label="Intensiteit" wire:model="default_intensity" placeholder="Kies niveau"
                                   :options="collect(range(1, 20))->mapWithKeys(fn ($i) => [$i => 'Niveau '.$i])"/>
                </x-form.section>
            @endif

            <x-slot:footer>
                <x-button variant="ghost" :href="#" wire:navigate>Annuleren</x-button>
                {{--                <x-button variant="ghost" :href="route('exercises.index')" wire:navigate>Annuleren</x-button>--}}
                <x-button icon="check" loading="save">Opslaan</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
