<?php

namespace App\Livewire\Workouts;

use App\Actions\StartWorkoutSession;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Workout $workout;

    public function mount(Workout $workout): void
    {
        $this->authorize('view', $workout);
        $this->workout = $workout;
    }

    public function archive(): void
    {
        $this->authorize('update', $this->workout);
        $this->workout->update(['archived_at' => $this->workout->isArchived() ? null : now()]);
    }

    /** Training starten; loopt er al een, dan ga je daar verder */
    public function start(StartWorkoutSession $start)
    {
        $this->authorize('update', $this->workout);

        if ($this->workout->isArchived() || $this->workout->exercises()->doesntExist()) {
            return;
        }

        $session = $start->handle(Auth::user(), $this->workout);

        return $this->redirectRoute('sessions.run', $session, navigate: true);
    }

    public function render()
    {
        $this->workout->load(['muscleGroups', 'exercises.exercise.muscleGroups']);

        return view('livewire.workouts.show', [
            'running' => WorkoutSession::where('user_id', Auth::id())->running()->first(),
        ])->title($this->workout->name);
    }
}
