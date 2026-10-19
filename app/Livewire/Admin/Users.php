<?php

namespace App\Livewire\Admin;

use App\Enums\SessionStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Admin: overzicht van de accounts, adminrechten geven of afnemen */
#[Layout('layouts.app')]
#[Title('Gebruikers')]
class Users extends Component
{
    public function mount(): void
    {
        Gate::authorize('admin');
    }

    public function toggleAdmin(int $id): void
    {
        Gate::authorize('admin');
        abort_if($id === Auth::id(), 403, 'Je kunt je eigen adminrechten niet afnemen.');

        $user = User::findOrFail($id);
        $user->forceFill(['is_admin' => ! $user->is_admin])->save();
        unset($this->users);
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->withCount(['workouts', 'workoutSessions as trainings_count' => fn ($q) => $q->where('status', SessionStatus::Completed)])
            ->withMax(['workoutSessions as last_training_at' => fn ($q) => $q->where('status', SessionStatus::Completed)], 'started_at')
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.users');
    }
}
