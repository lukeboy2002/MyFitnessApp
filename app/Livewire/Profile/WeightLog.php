<?php

namespace App\Livewire\Profile;

use App\Models\BodyWeight;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/** Gewicht bijhouden: één weging per dag, met verloop en verschil met je streefgewicht */
class WeightLog extends Component
{
    public string $date = '';

    public string $weight = '';

    public string $bodyFat = '';

    public function mount(): void
    {
        $this->date = today()->format('Y-m-d');
    }

    public function save(): void
    {
        $this->weight = str_replace(',', '.', trim($this->weight));
        $this->bodyFat = str_replace(',', '.', trim($this->bodyFat));

        $this->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'weight' => ['required', 'numeric', 'between:20,400'],
            'bodyFat' => ['nullable', 'numeric', 'between:2,70'],
        ], attributes: [
            'date' => 'datum',
            'weight' => 'gewicht',
            'bodyFat' => 'vetpercentage',
        ]);

        // Op dezelfde dag opnieuw wegen vervangt de vorige weging
        $weights = Auth::user()->bodyWeights();
        $entry = (clone $weights)->whereDate('measured_on', $this->date)->first() ?? $weights->make(['measured_on' => $this->date]);

        $entry->fill([
            'weight_kg' => round((float) $this->weight, 1),
            'body_fat' => $this->bodyFat === '' ? null : round((float) $this->bodyFat, 1),
        ])->save();

        $this->reset('weight', 'bodyFat');
        $this->date = today()->format('Y-m-d');
        unset($this->entries);
        $this->dispatch('body-weight-saved');
    }

    public function delete(int $id): void
    {
        Auth::user()->bodyWeights()->findOrFail($id)->delete();

        unset($this->entries);
        $this->dispatch('body-weight-saved');
    }

    /** Streefgewicht kan net zijn aangepast in Persoonlijke gegevens */
    #[On('personal-details-saved')]
    public function refreshTarget(): void {}

    /** @return Collection<int, BodyWeight> nieuwste eerst */
    #[Computed]
    public function entries(): Collection
    {
        return Auth::user()->bodyWeights()->orderByDesc('measured_on')->limit(60)->get();
    }

    public function render()
    {
        return view('livewire.profile.weight-log', [
            'target' => Auth::user()->target_weight_kg,
        ]);
    }
}
