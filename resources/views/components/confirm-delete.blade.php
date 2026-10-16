{{--
    Bevestigingsmodal voor verwijderen; hoort bij de trait App\Livewire\Concerns\ConfirmsDeletion.
    Knop: wire:click="confirmDelete({{ $model->id }})" · Modal: <x-confirm-delete :name="$deletingName" />
--}}
@props(['name' => '', 'title' => 'Verwijderen?'])

<x-modal wire:model="confirmingDeletion" :title="$title" maxWidth="md">
    <div class="flex items-start gap-4">
        <div class="grid size-11 shrink-0 place-items-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
            <x-icon name="trash" />
        </div>
        <div class="space-y-1 text-sm">
            <p class="text-base">Weet je zeker dat je <strong class="font-semibold">{{ $name }}</strong> wilt verwijderen?</p>
            <p class="text-slate-500 dark:text-slate-400">{{ $slot->isEmpty() ? 'Dit kan niet ongedaan worden gemaakt.' : $slot }}</p>
        </div>
    </div>

    <x-slot:footer>
        <x-button variant="ghost" type="button" wire:click="cancelDelete">Annuleren</x-button>
        <x-button variant="danger" type="button" icon="trash" wire:click="deleteConfirmed" loading="deleteConfirmed">Verwijderen</x-button>
    </x-slot:footer>
</x-modal>
