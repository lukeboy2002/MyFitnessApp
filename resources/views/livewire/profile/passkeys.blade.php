<div>
<x-card title="Passkeys" description="Inloggen met Face ID, Touch ID of je toegangscode, zonder wachtwoord.">
    <div class="flex flex-col gap-5">
        @if ($this->passkeys->isEmpty())
            <p class="text-sm text-slate-600 dark:text-slate-300">Je hebt nog geen passkeys.</p>
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
                                {{ $passkey->authenticator ?? 'Passkey' }} ·
                                {{ $passkey->last_used_at ? 'laatst gebruikt '.$passkey->last_used_at->diffForHumans() : 'nog niet gebruikt' }}
                            </p>
                        </div>
                        <x-button variant="ghost" size="sm" icon="trash" type="button" wire:click="delete({{ $passkey->id }})"
                                  wire:confirm="Passkey '{{ $passkey->name }}' verwijderen?" aria-label="Verwijderen">
                            <span class="sr-only sm:not-sr-only">Verwijderen</span>
                        </x-button>
                    </li>
                @endforeach
            </ul>
        @endif

        <div x-data="{ supported: !! window.PublicKeyCredential }">
            <x-alert type="warning" x-show="! supported" x-cloak>Deze browser ondersteunt geen passkeys.</x-alert>

            <form wire:submit="startRegistration" x-show="supported" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <x-form.input label="Naam voor de nieuwe passkey" wire:model="passkeyName" icon="key" class="sm:flex-1" />
                <x-button icon="plus" loading="startRegistration">Passkey toevoegen</x-button>
            </form>
        </div>

        @if ($failure)
            <x-alert type="danger">{{ $failure }}</x-alert>
        @endif
        <x-action-message on="saved">Passkey toegevoegd.</x-action-message>
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
                $wire.registrationFailed('Toevoegen geannuleerd.');
            } else if (error instanceof window.PasskeyExistsError) {
                $wire.registrationFailed('Deze passkey is al aan je account gekoppeld.');
            } else {
                $wire.registrationFailed(null);
            }
        }
    });
</script>
@endscript
</div>
