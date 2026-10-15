<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

    public function deleteUser(): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password:web'],
        ], [
            'password.current_password' => 'Dit wachtwoord klopt niet.',
        ]);

        $user = Auth::user();

        // Bestanden opruimen; database-rijen gaan via cascadeOnDelete mee
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }
        // Later: ook afbeeldingen van eigen oefeningen verwijderen, bv.
        // Storage::disk('public')->delete($user->exercises()->whereNotNull('image_path')->pluck('image_path')->all());

        Auth::guard('web')->logout();
        $user->delete();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: false);
    }

    public function render()
    {
        return view('livewire.profile.delete-user');
    }
}
