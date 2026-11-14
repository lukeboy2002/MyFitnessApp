<div>
    <x-card :title="__('Delete account')" :description="__('Your account, workouts, exercises and all training data will be permanently erased.')" class="ring-red-200 dark:ring-red-500/30">
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('This cannot be undone.') }}</p>

        <x-slot:footer>
            <x-button variant="danger" icon="trash" type="button" wire:click="confirmDeletion">{{ __('Delete account') }}</x-button>
        </x-slot:footer>
    </x-card>

    <x-modal wire:model="confirmingDeletion" :title="__('Are you sure?')" max-width="md">
        <form wire:submit="deleteUser" id="delete-user-form" class="flex flex-col gap-4">
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Enter your password to confirm that you want to permanently delete your account and all your data.') }}</p>
            <x-form.password :label="__('Password')" wire:model="password" id="delete-user-password" autocomplete="current-password" />
        </form>

        <x-slot:footer>
            <x-button variant="ghost" type="button" x-on:click="show = false">{{ __('Cancel') }}</x-button>
            <x-button variant="danger" form="delete-user-form" icon="trash" loading="deleteUser">{{ __('Delete permanently') }}</x-button>
        </x-slot:footer>
    </x-modal>
</div>
