<?php

namespace App\Livewire\Profile;

use App\Livewire\Profile\Concerns\ConfirmsPassword;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Passkeys beheren. Het registreren zelf gebeurt in de browser met
 * Passkeys.register() uit @laravel/passkeys (POST naar Fortify's passkey.store).
 */
class Passkeys extends Component
{
    use ConfirmsPassword;

    #[Validate('required|string|max:60')]
    public string $passkeyName = '';

    public ?string $failure = null;

    public function mount(): void
    {
        $this->passkeyName = $this->suggestedName();
    }

    /**
     * Stap 1: naam controleren en wachtwoord bevestigd? Dan mag de browser de passkey maken.
     */
    public function startRegistration(): void
    {
        $this->validate(attributes: ['passkeyName' => __('name')]);
        $this->failure = null;

        if (! $this->ensurePasswordIsConfirmed('startRegistration')) {
            return;
        }

        $this->dispatch('passkey-register', name: $this->passkeyName);
    }

    /**
     * Stap 2: de browser meldt dat de passkey is opgeslagen.
     */
    public function registered(): void
    {
        unset($this->passkeys);
        $this->passkeyName = $this->suggestedName();
        $this->dispatch('saved');
    }

    public function registrationFailed(?string $message = null): void
    {
        $this->failure = $message ?: __('Adding the passkey did not work.');
    }

    public function delete(int $id): void
    {
        if (! $this->ensurePasswordIsConfirmed('delete', ['id' => $id])) {
            return;
        }

        Auth::user()->passkeys()->whereKey($id)->delete();
        unset($this->passkeys);
    }

    #[Computed]
    public function passkeys()
    {
        return Auth::user()->passkeys()->latest()->get();
    }

    protected function suggestedName(): string
    {
        $agent = request()->userAgent() ?? '';

        $device = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Windows') => __('Windows PC'),
            default => __('My passkey'),
        };

        return __(':device of :name', ['device' => $device, 'name' => str(Auth::user()->name)->before(' ')]);
    }

    public function render()
    {
        return view('livewire.profile.passkeys');
    }
}
