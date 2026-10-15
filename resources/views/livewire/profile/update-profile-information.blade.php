@php use Illuminate\Contracts\Auth\MustVerifyEmail; @endphp
<x-card title="Profiel" description="Je naam en e-mailadres.">
    <form wire:submit="save" id="profile-information-form" class="grid gap-5 sm:grid-cols-2">
        <x-form.input label="Naam" wire:model="name" icon="user" required autocomplete="name"/>
        <x-form.input label="Gebruikersnaam" wire:model="username" icon="user" required autocomplete="username"/>
        <x-form.input label="E-mailadres" type="email" wire:model="email" icon="mail" required autocomplete="username"/>

        @if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())
            <x-alert type="warning" class="sm:col-span-2">
                Je e-mailadres is nog niet bevestigd.
                <button type="button" wire:click="resendVerification" class="font-semibold underline">Stuur de mail
                    opnieuw.
                </button>
                @if ($verificationLinkSent)
                    <span class="block">Er is een nieuwe link naar je e-mailadres gestuurd.</span>
                @endif
            </x-alert>
        @endif
    </form>

    <x-slot:footer>
        <x-button form="profile-information-form" icon="check" loading="save">Opslaan</x-button>
    </x-slot:footer>
</x-card>
