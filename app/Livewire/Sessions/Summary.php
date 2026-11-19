<?php

namespace App\Livewire\Sessions;

use App\Actions\ShareTraining;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\Achievements;
use App\Support\ExerciseRecords;
use App\Support\FriendActivity;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Summary extends Component
{
    public WorkoutSession $session;

    /** Notitie bij de training, bv. "knie voelde stijf". Eenmaal opgeslagen ligt hij vast. */
    public string $notes = '';

    /** Melding na het delen, bv. "Peter en Sanne kregen een melding" */
    public ?string $shareNotice = null;

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
        abort_if(filled($this->session->notes), 403, __('The note is already fixed.'));

        $this->notes = trim($this->notes);
        $this->validate(['notes' => ['required', 'string', 'max:1000']], attributes: ['notes' => 'notitie']);

        // Zonder updated_at: een notitie verandert niets aan badges en records, die hoeven niet opnieuw berekend
        WorkoutSession::withoutTimestamps(fn () => $this->session->update(['notes' => $this->notes]));
    }

    /** Delen met vrienden aan/uit (schakelaar op de samenvatting) */
    public function toggleShare(ShareTraining $share): void
    {
        $this->authorize('update', $this->session);
        abort_unless($this->canShare, 403);

        if ($this->session->isShared()) {
            $share->unshare($this->session);
            $this->shareNotice = null;

            return;
        }

        $notified = $share->share($this->session);
        $this->shareNotice = $notified
            ? __(':names got a notification.', ['names' => $this->friends->pluck('name')->join(', ', ' '.__('and').' ')])
            : null;
    }

    /** Je vrienden met een openbaar profiel (zij zien een gedeelde training) */
    #[Computed]
    public function friends()
    {
        return User::whereKey(FriendActivity::friendIds(Auth::user()))->orderBy('name')->get(['id', 'name']);
    }

    /** Delen kan met een openbaar profiel, minstens één vriend en een afgeronde training */
    #[Computed]
    public function canShare(): bool
    {
        return $this->session->user_id === Auth::id()
            && Auth::user()->profile_public
            && ! $this->session->isRunning()
            && ($this->session->isShared() || $this->friends->isNotEmpty());
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
