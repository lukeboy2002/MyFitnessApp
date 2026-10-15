<x-guest-layout title="Bevestig je wachtwoord"
                subtitle="Dit is een beveiligd onderdeel. Vul je wachtwoord in om door te gaan.">
    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-5">
        @csrf
        <x-form.password label="Wachtwoord" name="password" required autocomplete="current-password" autofocus/>
        <x-button block size="lg">Bevestigen</x-button>
    </form>
</x-guest-layout>
