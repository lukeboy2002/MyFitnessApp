<?php

namespace App\Livewire;

use App\Enums\ExperienceLevel;
use App\Enums\FitnessGoal;
use App\Support\StarterWorkout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Welkomstscherm na de eerste login: kies je doel en krijg een eerste workout */
#[Layout('layouts.app')]
class Welcome extends Component
{
    public string $goal = '';

    public string $experience = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->goal = $user->goal?->value ?? FitnessGoal::StayFit->value;
        $this->experience = $user->experience?->value ?? ExperienceLevel::Beginner->value;
    }

    #[Computed]
    public function starter(): StarterWorkout
    {
        return StarterWorkout::for(
            FitnessGoal::tryFrom($this->goal) ?? FitnessGoal::StayFit,
            ExperienceLevel::tryFrom($this->experience),
        );
    }

    /** Workout aanmaken en meteen bekijken */
    public function create()
    {
        $this->saveChoices();

        $workout = $this->starter->createFor(Auth::user());

        session()->flash('status', 'Je eerste workout staat klaar. Start hem als je in de sportschool bent.');

        return $this->redirectRoute('workouts.show', $workout, navigate: true);
    }

    public function skip()
    {
        $this->saveChoices();

        return $this->redirectRoute('dashboard', navigate: true);
    }

    private function saveChoices(): void
    {
        $this->validate([
            'goal' => ['required', Rule::enum(FitnessGoal::class)],
            'experience' => ['required', Rule::enum(ExperienceLevel::class)],
        ]);

        Auth::user()->update([
            'goal' => $this->goal,
            'experience' => $this->experience,
            'onboarded_at' => now(),
        ]);
    }

    public function render()
    {
        return view('livewire.welcome')->title('Welkom');
    }
}
