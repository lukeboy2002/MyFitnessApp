<?php

namespace App\Livewire\Sessions;

use App\Models\WorkoutSession;
use App\Support\Achievements;
use App\Support\ExerciseRecords;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Summary extends Component
{
    public WorkoutSession $session;

    /** Notitie bij de training, bv. "knie voelde stijf". Eenmaal opgeslagen ligt hij vast. */
    public string $notes = '';

    public function mount(WorkoutSession $session)
    {
        $this->authorize('view', $session);
        $this->session = $session;
        $this->notes = $session->notes ?? '';

        if ($session->isRunning()) {
            return $this->redirectRoute('sessions.run', $session, navigate: true);
        }
    }

    /** Notitie opslaan; daarna niet meer te wijzigen, zodat hij laat zien hoe het toen echt was */
    public function saveNotes(): void
    {
        $this->authorize('update', $this->session);
        abort_if(filled($this->session->notes), 403, 'De notitie ligt al vast.');

        $this->notes = trim($this->notes);
        $this->validate(['notes' => ['required', 'string', 'max:1000']], attributes: ['notes' => 'notitie']);

        // Zonder updated_at: een notitie verandert niets aan badges en records, die hoeven niet opnieuw berekend
        WorkoutSession::withoutTimestamps(fn () => $this->session->update(['notes' => $this->notes]));
    }

    public function render()
    {
        $this->session->load(['workout', 'exercises.exercise.muscleGroups', 'exercises.sets', 'exercises.cardio.splits']);
        $sets = $this->session->exercises->flatMap->sets;

        return view('livewire.sessions.summary', [
            'setsDone' => $sets->where('is_completed', true)->count(),
            'setsTotal' => $sets->count(),
            'records' => ExerciseRecords::forSession($this->session),
            'newBadges' => Achievements::earnedIn($this->session),
        ])->title($this->session->name);
    }
}
