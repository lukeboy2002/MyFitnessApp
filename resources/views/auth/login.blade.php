<x-guest-layout title="Inloggen" subtitle="Welkom terug! Log in om verder te trainen.">
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf

        <x-form.input label="Gebruikersnaam of e-mailadres" name="login" icon="user" :value="old('login')"
                      required autofocus autocomplete="username"/>

        <div class="flex flex-col gap-2">
            <x-form.password label="Wachtwoord" name="password" required autocomplete="current-password"/>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="self-end text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Wachtwoord
                    vergeten?</a>
            @endif
        </div>

        <x-form.checkbox name="remember" label="Onthoud mij"/>

        <x-button block size="lg" icon-right="chevron-right">Inloggen</x-button>
    </form>

    {{-- Passkeys via Fortify (Features::passkeys()) + npm-pakket @laravel/passkeys, zie resources/js/app.js --}}
    <div class="my-5 flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
        <span class="h-px flex-1 bg-slate-200 dark:bg-line-dark"></span> of <span
            class="h-px flex-1 bg-slate-200 dark:bg-line-dark"></span>
    </div>
    <x-button variant="secondary" block icon="key" type="button" :data-passkey-login="config('fortify.home')">Inloggen
        met passkey
    </x-button>
    <x-alert type="danger" id="passkey-error" :dismissible="false" class="mt-3" hidden>Inloggen met passkey is niet gelukt. Probeer het
        opnieuw of gebruik je wachtwoord.
    </x-alert>

    @if (Route::has('register') && \App\Support\Registration::isOpen())
        <x-slot:footer>
            Nog geen account? <a href="{{ route('register') }}"
                                 class="font-semibold text-brand-700 hover:underline dark:text-brand-300">Registreren</a>
        </x-slot:footer>
    @endif
</x-guest-layout>
