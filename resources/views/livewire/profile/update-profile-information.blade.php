@php use Illuminate\Contracts\Auth\MustVerifyEmail; @endphp
<x-card :title="__('Profile')" :description="__('Your name and email address.')">
    <form wire:submit="save" id="profile-information-form" class="grid gap-5 sm:grid-cols-2">
        <x-form.input :label="__('Name')" wire:model="name" icon="user" required autocomplete="name"/>
        <x-form.input :label="__('Username')" wire:model="username" icon="user" required autocomplete="username"/>
        <x-form.input :label="__('Email address')" type="email" wire:model="email" icon="mail" required autocomplete="username"/>

        @if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())
            <x-alert type="warning" class="sm:col-span-2">
                {{ __('Your email address has not been confirmed yet.') }}
                <button type="button" wire:click="resendVerification" class="font-semibold underline">{{ __('Send the email again.') }}</button>
                @if ($verificationLinkSent)
                    <span class="block">{{ __('A new link has been sent to your email address.') }}</span>
                @endif
            </x-alert>
        @endif
    </form>

    <x-slot:footer>
        <x-button form="profile-information-form" icon="check" loading="save">{{ __('Save') }}</x-button>
    </x-slot:footer>
</x-card>
