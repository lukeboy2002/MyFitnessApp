<?php

namespace App\Livewire\Progress;

use App\Enums\SessionStatus;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Support\TrainingStats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public const PERIODS = ['week' => 'Deze week', 'month' => 'Deze maand', 'year' => 'Dit jaar'];

    #[Url]
    public string $period = 'week';

    #[Url(except: '')]
    public string $search = '';

    public function updatedPeriod(): void
    {
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = 'week';
        }
    }

    public function from(): CarbonImmutable
    {
        return match ($this->period) {
            'month' => CarbonImmutable::now()->startOfMonth(),
            'year' => CarbonImmutable::now()->startOfYear(),
            default => CarbonImmutable::now()->startOfWeek(),
        };
    }

    /** Oefeningen die je ooit hebt gedaan, laatst gedane eerst, met hoe vaak */
    #[Computed]
    public function exercises(): Collection
    {
        $done = SessionExercise::query()
            ->join('workout_sessions', 'workout_sessions.id', '=', 'session_exercises.workout_session_id')
            ->where('workout_sessions.user_id', Auth::id())
            ->where('workout_sessions.status', SessionStatus::Completed)
            ->where(fn ($q) => $q->whereNotNull('session_exercises.completed_at')
                ->orWhereHas('sets', fn ($sets) => $sets->where('is_completed', true)))
            ->groupBy('session_exercises.exercise_id')
            ->selectRaw('session_exercises.exercise_id, count(*) as times, max(workout_sessions.started_at) as last_at')
            ->get()
            ->keyBy('exercise_id');

        return Exercise::query()
            ->whereIn('id', $done->keys())
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->with('muscleGroups')
            ->get()
            ->each(function (Exercise $exercise) use ($done) {
                $exercise->times = (int) $done[$exercise->id]->times;
                $exercise->last_at = CarbonImmutable::parse($done[$exercise->id]->last_at);
            })
            ->sortByDesc('last_at')
            ->values();
    }

    public function render()
    {
        $stats = new TrainingStats(Auth::user());

        return view('livewire.progress.index', [
            'totals' => $stats->totals($this->from()),
            'groups' => $stats->muscleGroups($this->from()),
            'months' => $stats->months(12),
        ])->title('Progress');
    }
}
