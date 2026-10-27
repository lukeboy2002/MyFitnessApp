<x-card title="Weekdoel" description="Hoeveel wil je per week trainen? Vul tijd, aantal trainingen of allebei in. Je ziet het terug op het dashboard.">
    <form wire:submit="save" id="weekly-goal-form" class="grid gap-5 sm:grid-cols-2">
        <x-form.input label="Trainingstijd per week" wire:model="hours" suffix="uur" inputmode="decimal" placeholder="bv. 5 of 4,5" hint="Leeg laten = geen tijdsdoel" />
        <x-form.input label="Trainingen per week" wire:model="sessions" type="number" inputmode="numeric" suffix="×" placeholder="bv. 3" hint="Leeg laten = geen aantal" />
    </form>

    <x-slot:footer>
        <x-action-message on="saved">Opgeslagen.</x-action-message>
        <x-button form="weekly-goal-form" icon="check" loading="save">Opslaan</x-button>
    </x-slot:footer>
</x-card>
