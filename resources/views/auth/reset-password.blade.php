<x-guest-layout :title="__('New password')">
    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ request()->route('token') }}">
        <x-form.input :label="__('Email address')" type="email" name="email" icon="mail" :value="old('email', request('email'))"
                      required/>
        <x-form.password :label="__('New password')" name="password" required autocomplete="new-password" autofocus/>
        <x-form.password :label="__('Confirm password')" name="password_confirmation" required
                         autocomplete="new-password"/>
        <x-button block size="lg">{{ __('Save password') }}</x-button>
    </form>
</x-guest-layout>
