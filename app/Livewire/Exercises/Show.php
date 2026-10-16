<?php

namespace App\Livewire\Exercises;

use App\Models\Exercise;
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
        return view('livewire.exercises.show')->title($this->exercise->name);
    }
}
