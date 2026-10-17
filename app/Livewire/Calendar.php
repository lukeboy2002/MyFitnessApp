<?php

namespace App\Livewire;

use App\Actions\StartWorkoutSession;
use App\Enums\SessionStatus;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Models\ScheduledWorkout;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Maandkalender met gedane trainingen en ingeplande workouts */
#[Layout('layouts.app')]
class Calendar extends Component
{
    use ConfirmsDeletion;

    /** Herhalen: aantal weken => label */
    public const REPEAT = [0 => 'Niet herhalen', 4 => '4 weken', 8 => '8 weken', 12 => '12 weken'];

    #[Url]
    public string $month = '';

    #[Url]
    public string $day = '';

    public bool $planning = false;

    public ?int $workout_id = null;

    public int $repeat = 0;

    public string $notes = '';

    public function mount(): void
    {
        $this->day = $this->validDate($this->day) ?? today()->toDateString();
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : substr($this->day, 0, 7);
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    public function goToday(): void
    {
        $this->day = today()->toDateString();
        $this->month = today()->format('Y-m');
    }

    public function select(string $date): void
    {
        if ($date = $this->validDate($date)) {
            $this->day = $date;
            $this->month = substr($date, 0, 7);
        }
    }

    public function openPlanning(): void
    {
        $this->resetValidation();
        $this->reset('workout_id', 'repeat', 'notes');
        $this->workout_id = $this->workouts->first()?->id;
        $this->planning = true;
    }

    public function plan(): void
    {
        $this->validate([
            'workout_id' => ['required', Rule::exists('workouts', 'id')->where('user_id', Auth::id())->whereNull('archived_at')],
            'repeat' => ['required', Rule::in(array_keys(self::REPEAT))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], attributes: ['workout_id' => 'workout']);

        $date = CarbonImmutable::parse($this->day);
        foreach (range(0, max(0, $this->repeat - 1)) as $week) {
            ScheduledWorkout::create([
                'user_id' => Auth::id(),
                'workout_id' => $this->workout_id,
                'scheduled_on' => $date->addWeeks($week),
                'notes' => $this->notes ?: null,
            ]);
        }

        $this->planning = false;
    }

    /** Ingeplande workout nu starten (loopt er al een training, dan ga je daar verder) */
    public function start(int $id, StartWorkoutSession $start)
    {
        $planned = $this->planned($id);
        abort_if($planned->workout->isArchived() || $planned->workout->exercises()->doesntExist(), 422);

        $session = $start->handle(Auth::user(), $planned->workout);
        if ($session->workout_id === $planned->workout_id && ! $planned->fresh()->isDone()) {
            $planned->update(['workout_session_id' => $session->id]);
        }

        return $this->redirectRoute('sessions.run', $session, navigate: true);
    }

    public function confirmDelete(int $id): void
    {
        $planned = $this->planned($id);

        $this->deletingId = $planned->id;
        $this->deletingName = $planned->workout->name.' op '.$planned->scheduled_on->translatedFormat('l j F');
        $this->confirmingDeletion = true;
    }

    public function delete(int $id): void
    {
        $this->planned($id)->delete();
    }

    protected function deletableModel(): string
    {
        return ScheduledWorkout::class;
    }

    /** Actieve workouts om in te plannen */
    #[Computed]
    public function workouts(): Collection
    {
        return Workout::where('user_id', Auth::id())->active()->orderBy('name')->get();
    }

    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->month)->startOfMonth();
    }

    public function render()
    {
        $first = $this->monthStart()->startOfWeek();
        $last = $this->monthStart()->endOfMonth()->endOfWeek();

        $sessions = WorkoutSession::where('user_id', Auth::id())
            ->whereIn('status', [SessionStatus::Completed, SessionStatus::Active, SessionStatus::Paused])
            ->whereBetween('started_at', [$first, $last->endOfDay()])
            ->withCount('exercises')
            ->orderBy('started_at')
            ->get()
            ->groupBy(fn (WorkoutSession $session) => $session->started_at->toDateString());

        $planned = ScheduledWorkout::where('user_id', Auth::id())
            ->whereBetween('scheduled_on', [$first->toDateString(), $last->toDateString()])
            ->with('workout')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (ScheduledWorkout $item) => $item->scheduled_on->toDateString());

        return view('livewire.calendar', [
            'days' => collect(range(0, $first->diffInDays($last)))->map(fn ($i) => $first->addDays($i)),
            'sessions' => $sessions,
            'planned' => $planned,
            'selected' => CarbonImmutable::parse($this->day),
        ])->title('Kalender');
    }

    private function planned(int $id): ScheduledWorkout
    {
        return ScheduledWorkout::where('user_id', Auth::id())->with('workout')->findOrFail($id);
    }

    private function validDate(string $date): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
            ? $date
            : null;
    }
}
