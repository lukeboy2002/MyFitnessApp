<?php

namespace App\Livewire;

use App\Support\Achievements;
use App\Support\BadgeProgress;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Badges: prestaties met niveaus, berekend uit alle afgeronde trainingen */
#[Layout('layouts.app')]
#[Title('Badges')]
class Badges extends Component
{
    public function render()
    {
        $badges = Achievements::for(Auth::user());

        return view('livewire.badges', [
            'badges' => $badges,
            'recent' => Achievements::recent($badges, 4),
            'earned' => $badges->sum(fn (BadgeProgress $badge) => $badge->level()),
            'total' => $badges->sum(fn (BadgeProgress $badge) => count($badge->thresholds)),
        ]);
    }
}
