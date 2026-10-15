<x-guest-layout title="Bevestig je e-mail"
                subtitle="We hebben je een mail gestuurd. Klik op de link in die mail om je account te activeren.">
    @if (session('status') === 'verification-link-sent')
        <x-alert type="success" class="mb-5">Er is een nieuwe verificatielink naar je e-mailadres gestuurd.</x-alert>
    @endif

    <div class="flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button block size="lg" icon="mail">Mail opnieuw versturen</x-button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button variant="ghost" block icon="logout">Uitloggen</x-button>
        </form>
    </div>
</x-guest-layout>
