<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Weekdoel: hoeveel uur en/of hoeveel trainingen je per week wilt doen (dashboard) */
class UpdateWeeklyGoal extends Component
{
    public string $hours = '';

    public string $sessions = '';

    public function mount(): void
    {
        $user = Auth::user();
        $minutes = $user->weekly_goal_minutes;
        $this->hours = $minutes ? str_replace('.', ',', (string) round($minutes / 60, 2)) : '';
        $this->sessions = (string) ($user->weekly_goal_sessions ?? '');
    }

    public function save(): void
    {
        $this->hours = str_replace(',', '.', trim($this->hours));
        $this->sessions = trim($this->sessions);

        $this->validate([
            'hours' => ['nullable', 'numeric', 'min:0.25', 'max:40'],
            'sessions' => ['nullable', 'integer', 'between:1,14'],
        ], attributes: ['hours' => 'weekdoel', 'sessions' => 'aantal trainingen']);

        Auth::user()->update([
            'weekly_goal_minutes' => $this->hours === '' ? null : (int) round((float) $this->hours * 60),
            'weekly_goal_sessions' => $this->sessions === '' ? null : (int) $this->sessions,
        ]);

        $this->hours = str_replace('.', ',', $this->hours);
        $this->dispatch('saved');
    }

    public function render()
    {
        return view('livewire.profile.update-weekly-goal');
    }
}
