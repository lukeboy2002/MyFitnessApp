<x-guest-layout title="Nieuw wachtwoord">
    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ request()->route('token') }}">
        <x-form.input label="E-mailadres" type="email" name="email" icon="mail" :value="old('email', request('email'))"
                      required/>
        <x-form.password label="Nieuw wachtwoord" name="password" required autocomplete="new-password" autofocus/>
        <x-form.password label="Wachtwoord bevestigen" name="password_confirmation" required
                         autocomplete="new-password"/>
        <x-button block size="lg">Wachtwoord opslaan</x-button>
    </form>
</x-guest-layout>
