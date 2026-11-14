<x-card :title="__('Change password')" :description="__('Use a long password that you do not use anywhere else.')">
    <form wire:submit="save" id="update-password-form" class="grid gap-5 sm:grid-cols-2">
        <x-form.password :label="__('Current password')" wire:model="current_password" autocomplete="current-password" class="sm:col-span-2 sm:max-w-[calc(50%-.625rem)]" />
        <x-form.password :label="__('New password')" wire:model="password" autocomplete="new-password" :hint="__('At least 8 characters')" />
        <x-form.password :label="__('Repeat new password')" wire:model="password_confirmation" autocomplete="new-password" />
    </form>

    <x-slot:footer>
        <x-action-message on="saved">{{ __('Password changed.') }}</x-action-message>
        <x-button form="update-password-form" icon="lock" loading="save">{{ __('Save password') }}</x-button>
    </x-slot:footer>
</x-card>
