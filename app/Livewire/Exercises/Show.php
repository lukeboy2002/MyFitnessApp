<?php

namespace App\Livewire\Exercises;

use App\Enums\SessionStatus;
use App\Models\Exercise;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Exercise $exercise;

    public function mount(Exercise $exercise): void
    {
        $this->authorize('view', $exercise);
        $this->exercise = $exercise->load('muscleGroups.bodyPart');
    }

    public function render()
    {
        return view('livewire.exercises.show', [
            'timesDone' => $this->exercise->sessionExercises()
                ->whereHas('session', fn ($q) => $q->where('user_id', Auth::id())->where('status', SessionStatus::Completed))
                ->count(),
        ])->title($this->exercise->name);
    }
}
