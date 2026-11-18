<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Openbaar profiel aan/uit (Profiel › Account en pagina Maatjes).
 * Openbaar = anderen vinden je bij Maatjes en je kunt workouts ontvangen. Trainingen blijven altijd privé.
 */
class PublicProfile extends Component
{
    public bool $public = false;

    public function mount(): void
    {
        $this->public = Auth::user()->profile_public;
    }

    public function updatedPublic(): void
    {
        Auth::user()->forceFill(['profile_public' => $this->public])->save();
        $this->dispatch('saved');
        $this->dispatch('profile-visibility-changed');
    }

    public function render()
    {
        return view('livewire.profile.public-profile');
    }
}
