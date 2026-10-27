<?php

namespace App\Livewire;

use App\Models\Exercise;
use App\Models\ScheduledWorkout;
use App\Models\WorkoutSession;
use App\Support\Achievements;
use App\Support\GoalProgress;
use App\Support\TrainingStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    /** Nieuwe gebruikers zien eerst het welkomstscherm */
    public function mount()
    {
        if (! Auth::user()->onboarded_at) {
            return $this->redirectRoute('welcome', navigate: true);
        }
    }

    public function render()
    {
        $user = Auth::user();
        $stats = new TrainingStats($user);
        $week = CarbonImmutable::now()->startOfWeek();

        return view('livewire.dashboard', [
            'user' => $user,
            'running' => WorkoutSession::where('user_id', $user->id)->running()->first(),
            'thisWeek' => $stats->totals($week),
            'lastWeek' => $stats->totals($week->subWeek(), $week),
            'weeks' => $stats->weeks(12),
            'recent' => $stats->recent(5),
            'next' => ScheduledWorkout::where('user_id', $user->id)->open()
                ->whereDate('scheduled_on', '>=', today())
                ->whereHas('workout', fn ($q) => $q->whereNull('archived_at'))
                ->with('workout')->orderBy('scheduled_on')->orderBy('id')->first(),
            // Eigen oefeningen: de 5 nieuwste, met het totaal voor de link naar de lijst
            'ownExercises' => Exercise::where('user_id', $user->id)->active()->with('muscleGroups')->latest()->limit(5)->get(),
            'ownExercisesCount' => Exercise::where('user_id', $user->id)->active()->count(),
            'badges' => Achievements::recent(Achievements::for($user), 3),
            'goalProgress' => GoalProgress::for($user),
        ])->title('Dashboard');
    }
}
