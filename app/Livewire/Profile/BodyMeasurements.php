<?php

namespace App\Livewire\Profile;

use App\Models\BodyMeasurement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Lichaamsmaten bijhouden: per dag één meting, met verloop per maat */
class BodyMeasurements extends Component
{
    public string $date = '';

    /** @var array<string, string> kolom => waarde zoals ingetypt */
    public array $values = [];

    public string $chartField = 'waist_cm';

    public function mount(): void
    {
        $this->date = today()->format('Y-m-d');
        $this->values = array_fill_keys(array_keys(BodyMeasurement::FIELDS), '');
    }

    public function save(): void
    {
        $this->values = array_map(fn ($value) => str_replace(',', '.', trim((string) $value)), $this->values);

        $this->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'values' => ['array'],
            ...collect(BodyMeasurement::FIELDS)->mapWithKeys(fn ($label, $field) => ["values.{$field}" => ['nullable', 'numeric', 'between:10,250']])->all(),
        ], attributes: [
            'date' => 'datum',
            ...collect(BodyMeasurement::FIELDS)->mapWithKeys(fn ($label, $field) => ["values.{$field}" => mb_strtolower($label)])->all(),
        ]);

        $filled = array_filter($this->values, fn ($value) => $value !== '');
        if (! $filled) {
            $this->addError('values', 'Vul minstens één maat in.');

            return;
        }

        // Meet je op dezelfde dag opnieuw, dan vullen de nieuwe maten die meting aan
        $measurements = Auth::user()->bodyMeasurements();
        $entry = (clone $measurements)->whereDate('measured_on', $this->date)->first() ?? $measurements->make(['measured_on' => $this->date]);
        $entry->fill(array_map(fn ($value) => round((float) $value, 1), $filled))->save();

        $this->values = array_fill_keys(array_keys(BodyMeasurement::FIELDS), '');
        $this->date = today()->format('Y-m-d');
        unset($this->entries);
        $this->dispatch('body-measurements-saved');
    }

    public function delete(int $id): void
    {
        Auth::user()->bodyMeasurements()->findOrFail($id)->delete();

        unset($this->entries);
    }

    /** @return Collection<int, BodyMeasurement> oudste eerst */
    #[Computed]
    public function entries(): Collection
    {
        return Auth::user()->bodyMeasurements()->orderBy('measured_on')->get();
    }

    /** Per maat de laatste waarde en het verschil met de eerste meting */
    #[Computed]
    public function summary(): array
    {
        return collect(BodyMeasurement::FIELDS)->map(function ($label, $field) {
            $measured = $this->entries->filter(fn ($entry) => $entry->{$field} !== null);
            $first = $measured->first();
            $last = $measured->last();

            return [
                'label' => $label,
                'value' => $last?->{$field},
                'change' => $last && $last->isNot($first) ? round($last->{$field} - $first->{$field}, 1) : null,
                'since' => $first?->measured_on,
            ];
        })->all();
    }

    public function render()
    {
        return view('livewire.profile.body-measurements', [
            'points' => $this->entries
                ->filter(fn ($entry) => $entry->{$this->chartField} !== null)
                ->map(fn ($entry) => ['date' => $entry->measured_on, 'value' => (float) $entry->{$this->chartField}])
                ->values(),
        ]);
    }
}
