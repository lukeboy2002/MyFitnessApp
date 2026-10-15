<?php

namespace App\Livewire\Profile\Concerns;

use Livewire\Attributes\Locked;

/**
 * Gevoelige acties (2FA, passkeys) vragen eerst om je wachtwoord,
 * net als Fortify's eigen 'password.confirm'-middleware.
 *
 * Het wachtwoord wordt in een modal gevraagd (<x-confirm-password-modal />),
 * waarna de oorspronkelijke actie direct verder gaat.
 */
trait ConfirmsPassword
{
    public bool $confirmingPassword = false;

    public string $confirmablePassword = '';

    /**
     * De actie die wacht op de wachtwoordbevestiging: [methode, parameters].
     *
     * @var array{0: string, 1: array<string, mixed>}|null
     */
    #[Locked]
    public ?array $pendingPasswordConfirmation = null;

    protected function passwordIsConfirmed(): bool
    {
        $confirmedAt = time() - session('auth.password_confirmed_at', 0);

        return $confirmedAt < config('auth.password_timeout', 10800);
    }

    /**
     * Opent de wachtwoord-modal als het wachtwoord niet (meer) bevestigd is.
     * Geeft false terug als de gebruiker eerst moet bevestigen.
     *
     * Na het bevestigen wordt $resumeWith met $parameters automatisch uitgevoerd,
     * zodat de gebruiker niet nog een keer hoeft te klikken.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function ensurePasswordIsConfirmed(?string $resumeWith = null, array $parameters = []): bool
    {
        if ($this->passwordIsConfirmed()) {
            return true;
        }

        $this->resetErrorBag('confirmablePassword');
        $this->confirmablePassword = '';
        $this->pendingPasswordConfirmation = $resumeWith ? [$resumeWith, $parameters] : null;
        $this->confirmingPassword = true;

        return false;
    }

    public function confirmPassword(): void
    {
        $this->validate([
            'confirmablePassword' => ['required', 'string', 'current_password:web'],
        ], [
            'confirmablePassword.required' => 'Vul je wachtwoord in.',
            'confirmablePassword.current_password' => 'Dit wachtwoord klopt niet.',
        ]);

        session()->put('auth.password_confirmed_at', time());

        $this->confirmingPassword = false;
        $this->confirmablePassword = '';

        if ($this->pendingPasswordConfirmation) {
            [$method, $parameters] = $this->pendingPasswordConfirmation;
            $this->pendingPasswordConfirmation = null;

            app()->call([$this, $method], $parameters);
        }
    }
}
