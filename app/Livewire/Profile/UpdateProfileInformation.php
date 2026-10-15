<?php

namespace App\Livewire\Profile;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Component;

class UpdateProfileInformation extends Component
{
    public string $name = '';

    public string $username = '';

    public string $email = '';

    public bool $verificationLinkSent = false;

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->username = Auth::user()->username;
        $this->email = Auth::user()->email;
    }

    /**
     * Gebruikt Fortify's UpdateUserProfileInformation-action. Bij een nieuw e-mailadres
     * wordt email_verified_at leeggemaakt en gaat er een nieuwe verificatiemail uit.
     */
    public function save(UpdatesUserProfileInformation $updater): void
    {
        $this->resetErrorBag();

        $updater->update(Auth::user(), [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
        ]);

        // Volledig herladen zodat naam en e-mail overal (sidebar, header) verversen
        session()->flash('status', 'Profiel bijgewerkt.');
        $this->redirectRoute('profile.edit', navigate: true);
    }

    public function resendVerification(): void
    {
        $user = Auth::user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
            $this->verificationLinkSent = true;
        }
    }

    public function render()
    {
        return view('livewire.profile.update-profile-information', [
            'user' => Auth::user(),
        ]);
    }
}
