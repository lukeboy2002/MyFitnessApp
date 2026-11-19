<?php

namespace App\Livewire\Sessions;

use App\Actions\PostTrainingHighlights;
use App\Exceptions\SessionNotRunning;
use App\Livewire\Concerns\EditsExerciseTargets;
use App\Livewire\Concerns\PicksExercises;
use App\Livewire\Sessions\Concerns\ArrangesExercises;
use App\Livewire\Sessions\Concerns\LogsCardio;
use App\Livewire\Sessions\Concerns\LogsSets;
use App\Livewire\Sessions\Concerns\SyncsOfflineChanges;
use App\Models\SessionExercise;
use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Support\ExerciseRecords;
use App\Support\RestReminder;
use App\Support\Routine;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Het trainingsscherm: timer met pauze, afronden en stoppen. Sets, cardio en het
 * ordenen van oefeningen staan in de traits in Sessions\Concerns.
 * Alles wordt meteen opgeslagen, zodat je kunt verversen of je telefoon kunt vergrendelen.
 * Zonder bereik bewaart de telefoon het en volgt het via SyncsOfflineChanges.
 */
#[Layout('layouts.app')]
class Run extends Component
{
    use ArrangesExercises, EditsExerciseTargets, LogsCardio, LogsSets, PicksExercises, SyncsOfflineChanges;

    public WorkoutSession $session;

    public bool $confirmingFinish = false;

    public bool $confirmingCancel = false;

    public function mount(WorkoutSession $session)
    {
        $this->authorize('update', $session);
        $this->session = $session;

        if (! $session->isRunning()) {
            return $this->redirectRoute('sessions.summary', $session, navigate: true);
        }

        $this->fillInputs();
    }

    /**
     * Elke wijziging controleert of de training nog loopt. Is hij intussen afgerond
     * (bijv. in een ander tabblad), dan wordt niets opgeslagen en ga je naar de samenvatting.
     */
    public function exception($e, $stopPropagation): void
    {
        if ($e instanceof SessionNotRunning) {
            $stopPropagation();
            session()->flash('status', __('This training is already completed. Your last change was not saved.'));
            $this->redirectRoute('sessions.summary', $this->session, navigate: true);
        }
    }

    /** Terug naar dit tabblad: meteen doorsturen als de training elders is afgerond */
    public function checkRunning(): void
    {
        $this->ensureRunning();
        $this->skipRender();
    }

    /** Rust overslaan (tik op de rusttimer): de pushmelding gaat dan niet meer */
    public function skipRest(): void
    {
        RestReminder::cancel(Auth::user());
        $this->skipRender();
    }

    public function pause(): void
    {
        $this->ensureRunning();
        RestReminder::cancel(Auth::user());
        $this->session->pause();
    }

    public function resume(): void
    {
        $this->ensureRunning();
        $this->session->resume();
    }

    public function finish(PostTrainingHighlights $highlights)
    {
        $this->ensureRunning();
        RestReminder::cancel(Auth::user());
        $this->session->finish();
        Routine::completed($this->session);
        $highlights->handle($this->session);

        return $this->redirectRoute('sessions.summary', $this->session, navigate: true);
    }

    /** Training stoppen zonder op te slaan */
    public function cancel()
    {
        $this->ensureRunning();
        RestReminder::cancel(Auth::user());
        $workout = $this->session->workout;
        $this->session->delete();

        session()->flash('status', __('The training was stopped and not saved.'));

        return $workout
            ? $this->redirectRoute('workouts.show', $workout, navigate: true)
            : $this->redirectRoute($this->session->isQuickstart() ? 'quickstart' : 'workouts.index', navigate: true);
    }

    public function render()
    {
        $this->session->load(['exercises.exercise.muscleGroups', 'exercises.exercise.equipment', 'exercises.sets', 'exercises.cardio.splits']);
        $sets = $this->session->exercises->flatMap->sets;

        return view('livewire.sessions.run', [
            'setsDone' => $sets->where('is_completed', true)->count(),
            'setsTotal' => $sets->count(),
            'records' => ExerciseRecords::forSession($this->session),
        ])->title($this->session->name);
    }

    private function fillInputs(): void
    {
        $this->session->load(['exercises.sets', 'exercises.cardio.splits']);

        foreach ($this->session->exercises as $item) {
            foreach ($item->sets as $set) {
                $this->values[$set->id] = $this->setValues($set);
            }

            $this->fillCardio($item);
        }
    }

    private function ensureRunning(): void
    {
        if (! $this->session->isRunning()) {
            throw new SessionNotRunning;
        }
    }

    private function findSet(int $id): SetLog
    {
        $this->ensureRunning();

        return SetLog::whereKey($id)
            ->whereHas('sessionExercise', fn ($q) => $q->where('workout_session_id', $this->session->id))
            ->with('sessionExercise.exercise')
            ->firstOrFail();
    }

    private function findExercise(int $id): SessionExercise
    {
        $this->ensureRunning();

        return $this->session->exercises()->with('exercise')->findOrFail($id);
    }

    /** "22,5" → "22.5"; lege invoer → null */
    private function decimal($value)
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return $value === '' ? null : $value;
    }
}
