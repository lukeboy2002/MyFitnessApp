<?php

namespace App\Livewire\Profile;

use App\Actions\DeleteUserAccount;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteUser extends Component
{
    public bool $confirmingDeletion = false;

    public string $password = '';

    public function confirmDeletion(): void
    {
        $this->resetErrorBag();
        $this->password = '';
        $this->confirmingDeletion = true;
    }

    public function deleteUser(DeleteUserAccount $delete): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password:web'],
        ], [
            'password.current_password' => 'Dit wachtwoord klopt niet.',
        ]);

        $user = Auth::user();

        Auth::guard('web')->logout();
        $delete->handle($user);

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: false);
    }

    public function render()
    {
        return view('livewire.profile.delete-user');
    }
}
