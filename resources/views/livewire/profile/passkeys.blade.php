<div>
<x-card :title="__('Passkeys')" :description="__('Log in with Face ID, Touch ID or your passcode, without a password.')">
    <div class="flex flex-col gap-5">
        @if ($this->passkeys->isEmpty())
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('You have no passkeys yet.') }}</p>
        @else
            <ul class="flex flex-col divide-y divide-slate-200 dark:divide-line-dark">
                @foreach ($this->passkeys as $passkey)
                    <li wire:key="passkey-{{ $passkey->id }}" class="flex items-center gap-3 py-3 first:pt-0">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                            <x-icon name="key" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $passkey->name }}</p>
                            <p class="truncate text-sm text-slate-500 dark:text-slate-400">
                                {{ $passkey->authenticator ?? __('Passkey') }} ·
                                {{ $passkey->last_used_at ? __('last used :time', ['time' => $passkey->last_used_at->diffForHumans()]) : __('not used yet') }}
                            </p>
                        </div>
                        <x-button variant="ghost" size="sm" icon="trash" type="button" wire:click="delete({{ $passkey->id }})"
                                  wire:confirm="{{ __('Delete passkey :name?', ['name' => $passkey->name]) }}" :aria-label="__('Remove')">
                            <span class="sr-only sm:not-sr-only">{{ __('Remove') }}</span>
                        </x-button>
                    </li>
                @endforeach
            </ul>
        @endif

        <div x-data="{ supported: !! window.PublicKeyCredential }">
            <x-alert type="warning" x-show="! supported" x-cloak>{{ __('This browser does not support passkeys.') }}</x-alert>

            <form wire:submit="startRegistration" x-show="supported" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <x-form.input :label="__('Name for the new passkey')" wire:model="passkeyName" icon="key" class="sm:flex-1" />
                <x-button icon="plus" loading="startRegistration">{{ __('Add passkey') }}</x-button>
            </form>
        </div>

        @if ($failure)
            <x-alert type="danger">{{ $failure }}</x-alert>
        @endif
        <x-action-message on="saved">{{ __('Passkey added.') }}</x-action-message>
    </div>
</x-card>

<x-confirm-password-modal id="passkeys"/>

@script
<script>
    $wire.on('passkey-register', async ({ name }) => {
        try {
            await window.Passkeys.register({ name });
            $wire.registered();
        } catch (error) {
            console.error(error);

            if (error instanceof window.UserCancelledError) {
                $wire.registrationFailed(@js(__('Adding was cancelled.')));
            } else if (error instanceof window.PasskeyExistsError) {
                $wire.registrationFailed(@js(__('This passkey is already linked to your account.')));
            } else {
                $wire.registrationFailed(null);
            }
        }
    });
</script>
@endscript
</div>
