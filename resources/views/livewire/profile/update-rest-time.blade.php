<x-card title="Rusttijd" description="Hoe lang je standaard rust tussen sets. Geldt voor oefeningen die je vanaf nu aan een workout of training toevoegt.">
    <form wire:submit="save" id="rest-time-form">
        <x-form.input label="Rust tussen sets" wire:model="seconds" type="number" inputmode="numeric" suffix="sec"
                      :placeholder="$appDefault" :hint="'Leeg laten = de standaard van de app ('.$appDefault.' sec)'" />
    </form>

    <x-slot:footer>
        <x-action-message on="saved">Opgeslagen.</x-action-message>
        <x-button form="rest-time-form" icon="check" loading="save">Opslaan</x-button>
    </x-slot:footer>
</x-card>
