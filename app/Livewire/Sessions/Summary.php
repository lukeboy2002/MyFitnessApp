<?php

namespace App\Livewire\Sessions;

use App\Models\WorkoutSession;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Summary extends Component
{
    public WorkoutSession $session;

    public function mount(WorkoutSession $session)
    {
        $this->authorize('view', $session);
        $this->session = $session;

        if ($session->isRunning()) {
            return $this->redirectRoute('sessions.run', $session, navigate: true);
        }
    }

    public function render()
    {
        $this->session->load(['workout', 'exercises.exercise.muscleGroups', 'exercises.sets', 'exercises.cardio.splits']);
        $sets = $this->session->exercises->flatMap->sets;

        return view('livewire.sessions.summary', [
            'setsDone' => $sets->where('is_completed', true)->count(),
            'setsTotal' => $sets->count(),
        ])->title($this->session->name);
    }
}
