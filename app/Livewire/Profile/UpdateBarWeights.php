<?php

namespace App\Livewire\Profile;

use App\Support\PlateCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Schijvencalculator: het gewicht van je stangen in de sportschool; leeg = de standaard */
class UpdateBarWeights extends Component
{
    /** ['bar' => '20', 'ez_bar' => '', 'trap_bar' => ''] */
    public array $weights = [];

    public function mount(): void
    {
        $own = Auth::user()->bar_weights ?? [];
        $this->weights = collect(PlateCalculator::BARS)->map(fn ($default, $type) => isset($own[$type]) ? (string) $own[$type] : '')->all();
    }

    public function save(): void
    {
        $this->weights = array_map(fn ($value) => str_replace(',', '.', trim((string) $value)), $this->weights);

        $this->validate(
            collect(PlateCalculator::BARS)->mapWithKeys(fn ($default, $type) => ["weights.{$type}" => ['nullable', 'numeric', 'between:0,50']])->all(),
            attributes: ['weights.bar' => __('Straight bar'), 'weights.ez_bar' => __('EZ bar'), 'weights.trap_bar' => __('Trap bar')],
        );

        $own = collect($this->weights)->filter(fn ($value) => $value !== '')->map(fn ($value) => (float) $value)->all();
        Auth::user()->update(['bar_weights' => $own ?: null]);

        $this->dispatch('saved');
    }

    public function render()
    {
        return view('livewire.profile.update-bar-weights', ['defaults' => PlateCalculator::BARS]);
    }
}
