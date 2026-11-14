{{--
    Wachtwoord-modal voor Livewire-componenten met de ConfirmsPassword-trait.
    Gebruik: <x-confirm-password-modal id="two-factor" /> binnen de root-div van het component.
    Het id moet uniek zijn op de pagina.
--}}
@props(['id'])

<x-modal wire:model="confirmingPassword" :title="__('Confirm your password')" max-width="md">
    <form wire:submit="confirmPassword" id="{{ $id }}-confirm-password" class="flex flex-col gap-4">
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('This is a secure area. Enter your password to continue.') }}</p>
        <x-form.password :label="__('Password')" wire:model="confirmablePassword" id="{{ $id }}-confirmable-password" autocomplete="current-password" />
    </form>

    <x-slot:footer>
        <x-button variant="ghost" type="button" x-on:click="show = false">{{ __('Cancel') }}</x-button>
        <x-button form="{{ $id }}-confirm-password" icon="check" loading="confirmPassword">{{ __('Confirm') }}</x-button>
    </x-slot:footer>
</x-modal>
