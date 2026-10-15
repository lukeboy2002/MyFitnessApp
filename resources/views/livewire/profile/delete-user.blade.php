<div>
    <x-card title="Account verwijderen" description="Je account, workouts, oefeningen en alle trainingsgegevens worden definitief gewist." class="ring-red-200 dark:ring-red-500/30">
        <p class="text-sm text-slate-600 dark:text-slate-300">Dit kan niet ongedaan worden gemaakt.</p>

        <x-slot:footer>
            <x-button variant="danger" icon="trash" type="button" wire:click="confirmDeletion">Account verwijderen</x-button>
        </x-slot:footer>
    </x-card>

    <x-modal wire:model="confirmingDeletion" title="Weet je het zeker?" max-width="md">
        <form wire:submit="deleteUser" id="delete-user-form" class="flex flex-col gap-4">
            <p class="text-sm text-slate-600 dark:text-slate-300">Vul je wachtwoord in om te bevestigen dat je je account en al je gegevens definitief wilt verwijderen.</p>
            <x-form.password label="Wachtwoord" wire:model="password" id="delete-user-password" autocomplete="current-password" />
        </form>

        <x-slot:footer>
            <x-button variant="ghost" type="button" x-on:click="show = false">Annuleren</x-button>
            <x-button variant="danger" form="delete-user-form" icon="trash" loading="deleteUser">Definitief verwijderen</x-button>
        </x-slot:footer>
    </x-modal>
</div>
