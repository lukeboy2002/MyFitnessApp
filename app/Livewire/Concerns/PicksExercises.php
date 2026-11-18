<?php

namespace App\Livewire\Concerns;

use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * De oefeningkiezer (<x-exercise-picker>): zoeken, filteren op type, spiergroep en eigen oefeningen.
 * Het component zelf bepaalt wat er gebeurt bij het kiezen (wire:click="pick(id)").
 */
trait PicksExercises
{
    public bool $showPicker = false;

    public string $pickerSearch = '';

    public string $pickerType = '';

    public ?int $pickerGroup = null;

    public bool $pickerOwn = false;

    public function openPicker(): void
    {
        $this->reset('pickerSearch', 'pickerType', 'pickerGroup', 'pickerOwn');
        $this->showPicker = true;
    }

    /** Voor wie de kiezer oefeningen toont; null = alleen de standaardlijst (kant-en-klare workout van de admin) */
    protected function pickerUser(): ?User
    {
        return Auth::user();
    }

    #[Computed]
    public function muscleGroups()
    {
        return MuscleGroup::visibleTo($this->pickerUser())->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Aantal actieve eigen oefeningen; 0 = filter niet tonen */
    #[Computed]
    public function pickerOwnCount(): int
    {
        return $this->pickerUser() ? Exercise::where('user_id', Auth::id())->active()->count() : 0;
    }

    #[Computed]
    public function pickerExercises()
    {
        return Exercise::visibleTo($this->pickerUser())->active()
            ->when($this->pickerSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->pickerSearch.'%'))
            ->when($this->pickerType !== '', fn ($q) => $q->where('type', $this->pickerType))
            ->when($this->pickerOwn, fn ($q) => $q->where('user_id', Auth::id()))
            ->when($this->pickerGroup, fn ($q) => $q->whereHas('muscleGroups', fn ($q) => $q->whereKey($this->pickerGroup)))
            ->with('muscleGroups')
            ->orderByRaw('user_id is null') // eigen oefeningen eerst
            ->orderBy('name')
            ->limit(40)
            ->get();
    }
}
