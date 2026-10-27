<?php

namespace App\Livewire\Profile;

use App\Models\WorkoutExercise;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Eigen standaard rusttijd tussen sets; leeg = de standaard van de app (Instellingen) */
class UpdateRestTime extends Component
{
    public string $seconds = '';

    public function mount(): void
    {
        $this->seconds = (string) (Auth::user()->default_rest_seconds ?? '');
    }

    public function save(): void
    {
        $this->seconds = trim($this->seconds);

        $this->validate(
            ['seconds' => ['nullable', 'integer', 'between:15,600']],
            attributes: ['seconds' => 'rusttijd'],
        );

        Auth::user()->update(['default_rest_seconds' => $this->seconds === '' ? null : (int) $this->seconds]);

        $this->dispatch('saved');
    }

    public function render()
    {
        return view('livewire.profile.update-rest-time', [
            'appDefault' => WorkoutExercise::defaultRestSeconds(),
        ]);
    }
}
