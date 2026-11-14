<x-guest-layout :title="__('Log in')" :subtitle="__('Welcome back! Log in to keep training.')">
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf

        <x-form.input :label="__('Username or email address')" name="login" icon="user" :value="old('login')"
                      required autofocus autocomplete="username"/>

        <div class="flex flex-col gap-2">
            <x-form.password :label="__('Password')" name="password" required autocomplete="current-password"/>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="self-end text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">{{ __('Forgot your password?') }}</a>
            @endif
        </div>

        <x-form.checkbox name="remember" :label="__('Remember me')"/>

        <x-button block size="lg" icon-right="chevron-right">{{ __('Log in') }}</x-button>
    </form>

    {{-- Passkeys via Fortify (Features::passkeys()) + npm-pakket @laravel/passkeys, zie resources/js/app.js --}}
    <div class="my-5 flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
        <span class="h-px flex-1 bg-slate-200 dark:bg-line-dark"></span> {{ __('or') }} <span
            class="h-px flex-1 bg-slate-200 dark:bg-line-dark"></span>
    </div>
    <x-button variant="secondary" block icon="key" type="button" :data-passkey-login="config('fortify.home')">{{ __('Log in with a passkey') }}</x-button>
    <x-alert type="danger" id="passkey-error" :dismissible="false" class="mt-3" hidden>{{ __('Logging in with a passkey did not work. Try again or use your password.') }}</x-alert>

    @if (Route::has('register') && \App\Support\Registration::isOpen())
        <x-slot:footer>
            {{ __('No account yet?') }} <a href="{{ route('register') }}"
                                 class="font-semibold text-brand-700 hover:underline dark:text-brand-300">{{ __('Register') }}</a>
        </x-slot:footer>
    @endif
</x-guest-layout>
