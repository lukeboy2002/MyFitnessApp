<?php

namespace App\Livewire\Profile;

use App\Enums\ExperienceLevel;
use App\Enums\FitnessGoal;
use App\Enums\Gender;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/** Persoonlijke gegevens: geboortedatum, geslacht, lengte, streefgewicht, doel en ervaring */
class UpdatePersonalDetails extends Component
{
    public string $birthDate = '';

    public string $gender = '';

    public string $heightCm = '';

    public string $targetWeight = '';

    public string $goal = '';

    public string $experience = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->birthDate = $user->birth_date?->format('Y-m-d') ?? '';
        $this->gender = $user->gender?->value ?? '';
        $this->heightCm = (string) ($user->height_cm ?? '');
        $this->targetWeight = $user->target_weight_kg ? str_replace('.', ',', (string) (float) $user->target_weight_kg) : '';
        $this->goal = $user->goal?->value ?? '';
        $this->experience = $user->experience?->value ?? '';
    }

    public function save(): void
    {
        $this->targetWeight = str_replace(',', '.', trim($this->targetWeight));

        $this->validate([
            'birthDate' => ['nullable', 'date', 'after:1900-01-01', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'heightCm' => ['nullable', 'integer', 'between:100,250'],
            'targetWeight' => ['nullable', 'numeric', 'between:30,300'],
            'goal' => ['nullable', Rule::enum(FitnessGoal::class)],
            'experience' => ['nullable', Rule::enum(ExperienceLevel::class)],
        ], attributes: [
            'birthDate' => 'geboortedatum',
            'gender' => 'geslacht',
            'heightCm' => 'lengte',
            'targetWeight' => 'streefgewicht',
            'goal' => 'doel',
            'experience' => 'ervaring',
        ]);

        Auth::user()->update([
            'birth_date' => $this->birthDate ?: null,
            'gender' => $this->gender ?: null,
            'height_cm' => $this->heightCm === '' ? null : (int) $this->heightCm,
            'target_weight_kg' => $this->targetWeight === '' ? null : round((float) $this->targetWeight, 1),
            'goal' => $this->goal ?: null,
            'experience' => $this->experience ?: null,
        ]);

        $this->targetWeight = str_replace('.', ',', $this->targetWeight);
        $this->dispatch('saved');
        $this->dispatch('personal-details-saved');
    }

    /** BMI opnieuw berekenen na een nieuwe weging */
    #[On('body-weight-saved')]
    public function refreshStats(): void {}

    public function render()
    {
        return view('livewire.profile.update-personal-details', [
            'user' => Auth::user()->load('latestBodyWeight'),
        ]);
    }
}
