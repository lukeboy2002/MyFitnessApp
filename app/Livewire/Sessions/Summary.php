<?php

namespace App\Livewire\Sessions;

use App\Models\WorkoutSession;
use App\Support\Achievements;
use App\Support\ExerciseRecords;
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
            'records' => ExerciseRecords::forSession($this->session),
            'newBadges' => Achievements::earnedIn($this->session),
        ])->title($this->session->name);
    }
}
