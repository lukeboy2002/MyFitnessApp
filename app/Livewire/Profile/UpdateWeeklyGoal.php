<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Weekdoel: hoeveel uur je per week wilt trainen (balk op het dashboard) */
class UpdateWeeklyGoal extends Component
{
    public string $hours = '';

    public function mount(): void
    {
        $minutes = Auth::user()->weekly_goal_minutes;
        $this->hours = $minutes ? str_replace('.', ',', (string) round($minutes / 60, 2)) : '';
    }

    public function save(): void
    {
        $this->hours = str_replace(',', '.', trim($this->hours));

        $this->validate(
            ['hours' => ['nullable', 'numeric', 'min:0.25', 'max:40']],
            attributes: ['hours' => 'weekdoel'],
        );

        Auth::user()->update([
            'weekly_goal_minutes' => $this->hours === '' ? null : (int) round((float) $this->hours * 60),
        ]);

        $this->hours = str_replace('.', ',', $this->hours);
        $this->dispatch('saved');
    }

    public function render()
    {
        return view('livewire.profile.update-weekly-goal');
    }
}
