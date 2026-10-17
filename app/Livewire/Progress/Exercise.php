<?php

namespace App\Livewire\Progress;

use App\Models\Exercise as ExerciseModel;
use App\Support\ExerciseProgress;
use App\Support\ExerciseRecords;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Voortgang van één oefening: records, grafiekje en een tabel met alle keren dat je hem deed */
#[Layout('layouts.app')]
class Exercise extends Component
{
    public ExerciseModel $exercise;

    public function mount(ExerciseModel $exercise): void
    {
        $this->authorize('view', $exercise);
        $this->exercise = $exercise;
    }

    public function render()
    {
        return view('livewire.progress.exercise', [
            'progress' => ExerciseProgress::for(Auth::user(), $this->exercise),
            'records' => ExerciseRecords::forUser(Auth::user(), $this->exercise),
        ])->title($this->exercise->name.' · Progress');
    }
}
