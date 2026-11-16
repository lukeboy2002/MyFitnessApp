<?php

namespace App\Livewire\Exercises;

use App\Models\Exercise;
use App\Models\ExerciseSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Jouw apparaatinstelling bij een oefening, op het trainingsscherm en op de oefeningpagina.
 * Tik om te wijzigen; opslaan bij Enter of als je het veld verlaat. Leeg maken = weghalen.
 */
class Setting extends Component
{
    #[Locked]
    public int $exerciseId;

    public string $body = '';

    public function mount(Exercise $exercise): void
    {
        $this->authorize('view', $exercise);
        $this->exerciseId = $exercise->id;
        $this->body = Auth::user()->exerciseSettings()->where('exercise_id', $exercise->id)->value('body') ?? '';
    }

    public function save(): void
    {
        $this->body = trim($this->body);
        $this->validate(['body' => ['nullable', 'string', 'max:'.ExerciseSetting::MAX]], attributes: ['body' => __('Machine setting')]);

        $settings = Auth::user()->exerciseSettings();
        $this->body === ''
            ? $settings->where('exercise_id', $this->exerciseId)->delete()
            : $settings->updateOrCreate(['exercise_id' => $this->exerciseId], ['body' => $this->body]);

        $this->dispatch('setting-saved');
    }

    public function render()
    {
        return view('livewire.exercises.setting');
    }
}
