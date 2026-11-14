<x-guest-layout :title="__('Two-factor authentication')">
    <div x-data="{ recovery: false }">
        <p class="mb-5 text-slate-500 dark:text-slate-400" x-show="! recovery">{{ __('Enter the code from your authenticator app.') }}</p>
        <p class="mb-5 text-slate-500 dark:text-slate-400" x-show="recovery" x-cloak>{{ __('Enter one of your recovery codes.') }}</p>

        <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-5">
            @csrf
            <div x-show="! recovery">
                <x-form.input :label="__('Code')" name="code" icon="shield" inputmode="numeric" autocomplete="one-time-code"
                              autofocus
                              class="[&_input]:tracking-[.4em]"/>
            </div>
            <div x-show="recovery" x-cloak>
                <x-form.input :label="__('Recovery code')" name="recovery_code" icon="key" autocomplete="one-time-code"/>
            </div>
            <x-button block size="lg">{{ __('Log in') }}</x-button>
        </form>

        <button type="button" x-on:click="recovery = ! recovery"
                class="mt-4 w-full text-center text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
            <span x-show="! recovery">{{ __('Use a recovery code') }}</span>
            <span x-show="recovery" x-cloak>{{ __('Use a code from your authenticator app') }}</span>
        </button>
    </div>
</x-guest-layout>
