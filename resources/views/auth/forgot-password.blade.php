<x-guest-layout :title="__('Forgot password')"
                :subtitle="__('Enter your email address and we will send you a link to choose a new password.')">
    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf
        <x-form.input :label="__('Email address')" type="email" name="email" icon="mail" :value="old('email')" required
                      autofocus/>
        <x-button block size="lg" icon="mail">{{ __('Send link') }}</x-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">{{ __('Back to login') }}</a>
    </x-slot:footer>
</x-guest-layout>
