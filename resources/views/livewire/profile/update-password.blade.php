<x-card title="Wachtwoord wijzigen" description="Gebruik een lang wachtwoord dat je nergens anders gebruikt.">
    <form wire:submit="save" id="update-password-form" class="grid gap-5 sm:grid-cols-2">
        <x-form.password label="Huidig wachtwoord" wire:model="current_password" autocomplete="current-password" class="sm:col-span-2 sm:max-w-[calc(50%-.625rem)]" />
        <x-form.password label="Nieuw wachtwoord" wire:model="password" autocomplete="new-password" hint="Minimaal 8 tekens" />
        <x-form.password label="Nieuw wachtwoord herhalen" wire:model="password_confirmation" autocomplete="new-password" />
    </form>

    <x-slot:footer>
        <x-action-message on="saved">Wachtwoord gewijzigd.</x-action-message>
        <x-button form="update-password-form" icon="lock" loading="save">Wachtwoord opslaan</x-button>
    </x-slot:footer>
</x-card>
