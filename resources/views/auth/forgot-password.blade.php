<x-guest-layout title="Wachtwoord vergeten"
                subtitle="Vul je e-mailadres in, dan sturen we je een link om een nieuw wachtwoord te kiezen.">
    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf
        <x-form.input label="E-mailadres" type="email" name="email" icon="mail" :value="old('email')" required
                      autofocus/>
        <x-button block size="lg" icon="mail">Link versturen</x-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">Terug
            naar inloggen</a>
    </x-slot:footer>
</x-guest-layout>
