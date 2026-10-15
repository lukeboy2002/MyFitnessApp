<?php

namespace App\Livewire\Profile;

use App\Livewire\Profile\Concerns\ConfirmsPassword;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TwoFactorAuthentication extends Component
{
    use ConfirmsPassword;

    public bool $showingQrCode = false;

    public bool $showingConfirmation = false;

    public bool $showingRecoveryCodes = false;

    public string $code = '';

    public function mount(): void
    {
        // Half afgemaakte setup (QR getoond, nooit bevestigd) opruimen
        $user = Auth::user();

        if ($this->requiresConfirmation() && $user->two_factor_secret && is_null($user->two_factor_confirmed_at)) {
            app(DisableTwoFactorAuthentication::class)($user);
        }
    }

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        if (! $this->ensurePasswordIsConfirmed('enable')) {
            return;
        }

        $enable(Auth::user());

        $this->showingQrCode = true;
        $this->showingConfirmation = $this->requiresConfirmation();
        $this->showingRecoveryCodes = ! $this->showingConfirmation;
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        if (! $this->ensurePasswordIsConfirmed('confirm')) {
            return;
        }

        $confirm(Auth::user(), $this->code);

        $this->reset('code', 'showingQrCode', 'showingConfirmation');
        $this->showingRecoveryCodes = true;
    }

    public function showRecoveryCodes(): void
    {
        if ($this->ensurePasswordIsConfirmed('showRecoveryCodes')) {
            $this->showingRecoveryCodes = true;
        }
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        if (! $this->ensurePasswordIsConfirmed('regenerateRecoveryCodes')) {
            return;
        }

        $generate(Auth::user());
        $this->showingRecoveryCodes = true;
    }

    public function cancel(DisableTwoFactorAuthentication $disable): void
    {
        $disable(Auth::user());
        $this->reset('code', 'showingQrCode', 'showingConfirmation', 'showingRecoveryCodes');
    }

    public function disable(DisableTwoFactorAuthentication $disable): void
    {
        if (! $this->ensurePasswordIsConfirmed('disable')) {
            return;
        }

        $disable(Auth::user());
        $this->reset('code', 'showingQrCode', 'showingConfirmation', 'showingRecoveryCodes');
    }

    #[Computed]
    public function enabled(): bool
    {
        $user = Auth::user();

        return ! empty($user->two_factor_secret)
            && (! $this->requiresConfirmation() || ! is_null($user->two_factor_confirmed_at));
    }

    #[Computed]
    public function setupKey(): ?string
    {
        $secret = Auth::user()->two_factor_secret;

        return $secret ? Fortify::currentEncrypter()->decrypt($secret) : null;
    }

    protected function requiresConfirmation(): bool
    {
        return Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
    }

    public function render()
    {
        return view('livewire.profile.two-factor-authentication', [
            'user' => Auth::user()->fresh(),
        ]);
    }
}
