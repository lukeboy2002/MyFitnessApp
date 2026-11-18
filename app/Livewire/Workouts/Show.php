<?php

namespace App\Livewire\Workouts;

use App\Actions\AdoptWorkout;
use App\Actions\ShareWorkout;
use App\Actions\StartWorkoutSession;
use App\Livewire\Workouts\Concerns\HandlesSharedWorkouts;
use App\Models\SharedWorkout;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    use HandlesSharedWorkouts;

    public Workout $workout;

    public bool $showShare = false;

    /** @var list<int> ids van maatjes */
    public array $shareTo = [];

    public string $shareMessage = '';

    public function mount(Workout $workout): void
    {
        $this->authorize('view', $workout);
        $this->workout = $workout;
    }

    public function archive(): void
    {
        $this->authorize('update', $this->workout);
        $this->workout->update(['archived_at' => $this->workout->isArchived() ? null : now()]);
    }

    public function openShare(): void
    {
        $this->authorize('share', $this->workout);
        $this->reset('shareTo', 'shareMessage');
        $this->resetValidation();
        $this->showShare = true;
    }

    /** Workout naar de gekozen maatjes sturen */
    public function share(ShareWorkout $share): void
    {
        $this->authorize('share', $this->workout);

        $this->validate([
            'shareTo' => ['required', 'array', 'min:1'],
            'shareTo.*' => ['integer'],
            'shareMessage' => ['nullable', 'string', 'max:300'],
        ], ['shareTo.required' => __('Choose at least one buddy.'), 'shareTo.min' => __('Choose at least one buddy.')], ['shareMessage' => __('share message')]);

        $recipients = $this->buddies->whereIn('id', $this->shareTo);
        $sent = $share->handle(Auth::user(), $this->workout, $recipients, $this->shareMessage);

        $this->showShare = false;
        session()->flash('status', trans_choice(':name is sent to :count buddy.|:name is sent to :count buddies.', $sent, ['name' => $this->workout->name]));
        $this->redirectRoute('workouts.show', $this->workout, navigate: true);
    }

    #[Computed]
    public function buddies()
    {
        return Auth::user()->buddies()->where('profile_public', true)->values();
    }

    /** Kant-en-klare workout overnemen en meteen naar je eigen kopie */
    public function adopt(AdoptWorkout $adopt)
    {
        $this->authorize('adopt', $this->workout);

        $copy = $adopt->handle(Auth::user(), $this->workout);
        session()->flash('status', __(':name is now in your workouts.', ['name' => $copy->name]));

        return $this->redirectRoute('workouts.show', $copy, navigate: true);
    }

    /** Training starten; loopt er al een, dan ga je daar verder */
    public function start(StartWorkoutSession $start)
    {
        $this->authorize('start', $this->workout);

        if ($this->workout->isArchived() || $this->workout->exercises()->doesntExist()) {
            return;
        }

        $session = $start->handle(Auth::user(), $this->workout);

        return $this->redirectRoute('sessions.run', $session, navigate: true);
    }

    public function render()
    {
        $this->workout->load(['muscleGroups', 'exercises.exercise.muscleGroups']);

        return view('livewire.workouts.show', [
            'running' => WorkoutSession::where('user_id', Auth::id())->running()->first(),
            'copy' => $this->workout->isStandard()
                ? $this->workout->copies()->where('user_id', Auth::id())->active()->latest('id')->first()
                : null,
            'canManage' => $this->workout->isManageableBy(Auth::user()),
            'incoming' => $this->workout->isOwnedBy(Auth::user()) || $this->workout->isStandard()
                ? null
                : SharedWorkout::open()->where('workout_id', $this->workout->id)->where('recipient_id', Auth::id())->with('sender')->latest('id')->first(),
            'ownExercises' => $this->workout->exercises->filter(fn ($item) => ! $item->exercise->isStandard())->pluck('exercise.name')->unique()->values(),
        ])->title($this->workout->name);
    }
}
