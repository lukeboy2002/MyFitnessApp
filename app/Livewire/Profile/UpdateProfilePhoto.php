<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class UpdateProfilePhoto extends Component
{
    use WithFileUploads;

    #[Validate('required|image|mimes:jpg,jpeg,png,webp|max:2048')]
    public $photo = null;

    /**
     * Direct opslaan zodra de upload klaar is.
     */
    public function updatedPhoto(): void
    {
        $this->validate();

        $user = Auth::user();
        $old = $user->profile_photo_path;

        $user->forceFill([
            'profile_photo_path' => $this->photo->storePublicly('profile-photos', 'public'),
        ])->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $this->reset('photo');

        // Volledig herladen zodat ook de avatar in de sidebar ververst
        session()->flash('status', 'Profielfoto bijgewerkt.');
        $this->redirectRoute('profile.edit', navigate: true);
    }

    public function deletePhoto(): void
    {
        $user = Auth::user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->forceFill(['profile_photo_path' => null])->save();
        }

        session()->flash('status', 'Profielfoto verwijderd.');
        $this->redirectRoute('profile.edit', navigate: true);
    }

    public function render()
    {
        return view('livewire.profile.update-profile-photo', [
            'user' => Auth::user(),
        ]);
    }
}
