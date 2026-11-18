<?php

namespace App\Livewire\Workouts;

use App\Actions\AdoptWorkout;
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

    /** Kant-en-klare workout overnemen en meteen naar je eigen kopie */
    public function adopt(AdoptWorkout $adopt)
    {
        $this->authorize('adopt', $this->workout);

        $copy = $adopt->handle(Auth::user(), $this->workout);
        session()->flash('status', __(':name is now in your workouts.', ['name' => $copy->name]));

        return $this->redirectRoute('workouts.show', $copy, navigate: true);
    }

    /** Training starten; loopt er al een, dan ga je daar verder */
    public function start(StartWorkoutSession $start)
    {
        $this->authorize('start', $this->workout);

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
            'copy' => $this->workout->isStandard()
                ? $this->workout->copies()->where('user_id', Auth::id())->active()->latest('id')->first()
                : null,
            'canManage' => $this->workout->isManageableBy(Auth::user()),
        ])->title($this->workout->name);
    }
}
