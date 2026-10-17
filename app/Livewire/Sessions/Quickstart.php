<?php

namespace App\Livewire\Sessions;

use App\Actions\StartWorkoutSession;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Quickstart: direct een vrije training starten, de oefeningen voeg je tijdens de training toe */
#[Layout('layouts.app')]
class Quickstart extends Component
{
    public function start(StartWorkoutSession $action)
    {
        $session = $action->quickstart(Auth::user());

        return $this->redirectRoute('sessions.run', $session, navigate: true);
    }

    public function render()
    {
        return view('livewire.sessions.quickstart', [
            'running' => WorkoutSession::where('user_id', Auth::id())->running()->first(),
        ])->title('Quickstart');
    }
}
