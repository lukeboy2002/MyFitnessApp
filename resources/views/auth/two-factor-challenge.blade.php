<x-guest-layout title="Tweestapsverificatie">
    <div x-data="{ recovery: false }">
        <p class="mb-5 text-slate-500 dark:text-slate-400" x-show="! recovery">Vul de code uit je authenticator-app
            in.</p>
        <p class="mb-5 text-slate-500 dark:text-slate-400" x-show="recovery" x-cloak>Vul een van je herstelcodes in.</p>

        <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-5">
            @csrf
            <div x-show="! recovery">
                <x-form.input label="Code" name="code" icon="shield" inputmode="numeric" autocomplete="one-time-code"
                              autofocus
                              class="[&_input]:tracking-[.4em]"/>
            </div>
            <div x-show="recovery" x-cloak>
                <x-form.input label="Herstelcode" name="recovery_code" icon="key" autocomplete="one-time-code"/>
            </div>
            <x-button block size="lg">Inloggen</x-button>
        </form>

        <button type="button" x-on:click="recovery = ! recovery"
                class="mt-4 w-full text-center text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
            <span x-show="! recovery">Herstelcode gebruiken</span>
            <span x-show="recovery" x-cloak>Code uit authenticator-app gebruiken</span>
        </button>
    </div>
</x-guest-layout>
