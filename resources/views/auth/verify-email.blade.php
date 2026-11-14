<x-guest-layout :title="__('Confirm your email')"
                :subtitle="__('We have sent you an email. Click the link in that email to activate your account.')">
    @if (session('status') === 'verification-link-sent')
        <x-alert type="success" class="mb-5">{{ __('A new verification link has been sent to your email address.') }}</x-alert>
    @endif

    <div class="flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button block size="lg" icon="mail">{{ __('Resend email') }}</x-button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button variant="ghost" block icon="logout">{{ __('Log out') }}</x-button>
        </form>
    </div>
</x-guest-layout>
