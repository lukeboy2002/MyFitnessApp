<div>
<x-card :title="__('Two-factor authentication')"
        :description="__('Besides your password, the app asks for a code from your authenticator app when you log in.')">
    <x-slot:actions>
        @if ($this->enabled)
            <x-badge color="success" icon="shield">{{ __('On') }}</x-badge>
        @elseif ($showingConfirmation)
            <x-badge color="fire">{{ __('Setting up…') }}</x-badge>
        @else
            <x-badge color="danger">{{ __('Off') }}</x-badge>
        @endif
    </x-slot:actions>

    <div class="flex flex-col gap-5">
        @if ($showingQrCode)
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                <div class="w-fit shrink-0 rounded-2xl bg-white p-3 ring-1 ring-slate-200 [&_svg]:size-44">
                    {!! $user->twoFactorQrCodeSvg() !!}
                </div>
                <div class="flex min-w-0 flex-col gap-3 text-sm">
                    <p>{{ __('Scan this QR code with an authenticator app, such as Google Authenticator, 1Password or the built-in codes in iOS Passwords.') }}</p>
                    <p class="text-slate-500 dark:text-slate-400">{{ __('Or enter this key manually:') }}</p>
                    <code
                        class="w-fit max-w-full break-all rounded-lg bg-slate-100 px-3 py-2 font-mono text-sm font-semibold tracking-wider dark:bg-white/10">{{ $this->setupKey }}</code>
                </div>
            </div>

            @if ($showingConfirmation)
                <form wire:submit="confirm" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <x-form.input :label="__('Code from the app')" wire:model="code" icon="shield" inputmode="numeric"
                                  autocomplete="one-time-code"
                                  maxlength="6" class="sm:w-56 [&_input]:tracking-[.3em]" autofocus/>
                    <div class="flex gap-2">
                        <x-button icon="check" loading="confirm">{{ __('Confirm') }}</x-button>
                        <x-button variant="ghost" type="button" wire:click="cancel">{{ __('Cancel') }}</x-button>
                    </div>
                </form>
            @endif
        @endif

        @if ($showingRecoveryCodes && $this->enabled)
            <div class="flex flex-col gap-3">
                <x-alert type="warning">{{ __('Keep these recovery codes in a safe place, for example in your password manager. Each code works once if you lose your phone.') }}</x-alert>
                <div x-data="{ copied: false }" class="rounded-2xl bg-slate-100 p-4 dark:bg-white/5">
                    <ul class="grid grid-cols-2 gap-2 font-mono text-sm font-semibold tabular-nums">
                        @foreach ($user->recoveryCodes() as $recoveryCode)
                            <li>{{ $recoveryCode }}</li>
                        @endforeach
                    </ul>
                    <button type="button"
                            class="mt-3 flex items-center gap-1.5 text-sm font-semibold text-brand-700 dark:text-brand-300"
                            x-on:click="navigator.clipboard.writeText(@js(implode("\n", $user->recoveryCodes()))).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                        <x-icon name="check" class="size-4" x-show="copied" x-cloak/>
                        <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                    </button>
                </div>
            </div>
        @endif

        @unless ($this->enabled || $showingQrCode)
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Two-factor authentication is off. Turn it on for extra security for your account.') }}</p>
        @endunless
    </div>

    @if ($this->enabled || ! $showingQrCode)
        <x-slot:footer>
            @if (! $this->enabled && ! $showingQrCode)
                <x-button icon="shield" wire:click="enable" type="button" loading="enable">{{ __('Enable') }}</x-button>
            @elseif ($this->enabled)
                @if ($showingRecoveryCodes)
                    <x-button variant="secondary" type="button" wire:click="regenerateRecoveryCodes"
                              loading="regenerateRecoveryCodes">{{ __('New recovery codes') }}</x-button>
                @else
                    <x-button variant="secondary" type="button" wire:click="showRecoveryCodes">{{ __('Show recovery codes') }}</x-button>
                @endif
                <x-button variant="danger" type="button" wire:click="disable" loading="disable"
                          wire:confirm="{{ __('Are you sure you want to turn off two-factor authentication?') }}">{{ __('Disable') }}</x-button>
            @endif
        </x-slot:footer>
    @endif
</x-card>

<x-confirm-password-modal id="two-factor"/>
</div>
