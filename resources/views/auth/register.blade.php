<x-guest-layout title="Account maken" subtitle="Na registratie ontvang je een mail om je e-mailadres te bevestigen.">
    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-5">
        @csrf
        <x-form.input label="Naam" name="name" icon="user" :value="old('name')" required autofocus autocomplete="name"/>
        <x-form.input label="Gebruikersnaam" name="username" icon="user" :value="old('username')" required
                      autocomplete="username"/>
        <x-form.input label="E-mailadres" type="email" name="email" icon="mail" :value="old('email')" required
                      autocomplete="username"/>
        <x-form.password label="Wachtwoord" name="password" required autocomplete="new-password"
                         hint="Minimaal 8 tekens"/>
        <x-form.password label="Wachtwoord bevestigen" name="password_confirmation" required
                         autocomplete="new-password"/>
        <x-button block size="lg">Account maken</x-button>
    </form>

    <x-slot:footer>
        Al een account? <a href="{{ route('login') }}"
                           class="font-semibold text-brand-700 hover:underline dark:text-brand-300">Inloggen</a>
    </x-slot:footer>
</x-guest-layout>
