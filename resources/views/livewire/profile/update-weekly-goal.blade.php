<x-card id="weekdoel" title="Weekdoel" description="Hoeveel wil je per week trainen? Je ziet het terug op het dashboard.">
    <form wire:submit="save" id="weekly-goal-form">
        <x-form.input label="Trainingstijd per week" wire:model="hours" suffix="uur" inputmode="decimal" placeholder="bv. 5 of 4,5" hint="Leeg laten = geen weekdoel" />
    </form>

    <x-slot:footer>
        <x-action-message on="saved">Opgeslagen.</x-action-message>
        <x-button form="weekly-goal-form" icon="check" loading="save">Opslaan</x-button>
    </x-slot:footer>
</x-card>
