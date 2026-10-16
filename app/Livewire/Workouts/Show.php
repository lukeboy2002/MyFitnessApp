<?php

namespace App\Livewire\Workouts;

use App\Models\Workout;
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

    public function render()
    {
        $this->workout->load(['muscleGroups', 'exercises.exercise.muscleGroups']);

        return view('livewire.workouts.show')->title($this->workout->name);
    }
}
