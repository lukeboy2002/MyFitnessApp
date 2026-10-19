<?php

namespace App\Livewire\Concerns;

use App\Models\Exercise;
use App\Models\MuscleGroup;
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

    #[Computed]
    public function muscleGroups()
    {
        return MuscleGroup::visibleTo(Auth::user())->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Aantal actieve eigen oefeningen; 0 = filter niet tonen */
    #[Computed]
    public function pickerOwnCount(): int
    {
        return Exercise::where('user_id', Auth::id())->active()->count();
    }

    #[Computed]
    public function pickerExercises()
    {
        return Exercise::visibleTo(Auth::user())->active()
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
