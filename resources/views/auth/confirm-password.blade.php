<x-guest-layout :title="__('Confirm your password')"
                :subtitle="__('This is a secure area. Enter your password to continue.')">
    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-5">
        @csrf
        <x-form.password :label="__('Password')" name="password" required autocomplete="current-password" autofocus/>
        <x-button block size="lg">{{ __('Confirm') }}</x-button>
    </form>
</x-guest-layout>
