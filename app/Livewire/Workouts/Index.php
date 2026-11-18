<?php

namespace App\Livewire\Workouts;

use App\Actions\AdoptWorkout;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Livewire\Workouts\Concerns\HandlesSharedWorkouts;
use App\Models\BodyPart;
use App\Models\Equipment;
use App\Models\SharedWorkout;
use App\Models\Workout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ConfirmsDeletion, HandlesSharedWorkouts;

    #[Url(as: 'zoek', except: '')]
    public string $search = '';

    #[Url(as: 'archief', except: false)]
    public bool $showArchived = false;

    /** Tab Kant-en-klaar: de standaardworkouts */
    #[Url(as: 'kant-en-klaar', except: false)]
    public bool $showReady = false;

    /** Tab Ontvangen: workouts die maatjes je stuurden */
    #[Url(as: 'ontvangen', except: false)]
    public bool $showReceived = false;

    #[Url(as: 'deel')]
    public ?int $bodyPart = null;

    #[Url(as: 'materiaal')]
    public ?int $equipmentFilter = null;

    public ?string $message = null;

    protected function deletableModel(): string
    {
        return Workout::class;
    }

    public function showTab(string $tab): void
    {
        $this->showArchived = $tab === 'archived';
        $this->showReady = $tab === 'ready';
        $this->showReceived = $tab === 'received';
        $this->reset('search', 'bodyPart', 'equipmentFilter');
        unset($this->workouts);
    }

    /** Kant-en-klare workout overnemen als eigen workout */
    public function adopt(int $id, AdoptWorkout $adopt)
    {
        $workout = Workout::whereNull('user_id')->findOrFail($id);
        $this->authorize('adopt', $workout);

        $copy = $adopt->handle(Auth::user(), $workout);
        session()->flash('status', __(':name is now in your workouts.', ['name' => $copy->name]));

        return $this->redirectRoute('workouts.show', $copy, navigate: true);
    }

    /** Admin: eigen workout kopiëren naar de kant-en-klare workouts */
    public function copyAsStandard(int $id)
    {
        $this->authorize('createStandard', Workout::class);
        $workout = $this->find($id);

        if (! $workout->onlyStandardExercises()) {
            $this->message = null;
            $this->addError('standard', __('Only workouts with standard exercises can become a ready-made workout.'));

            return;
        }

        $copy = $workout->duplicate($workout->name, standard: true);
        session()->flash('status', __(':name is now a ready-made workout.', ['name' => $copy->name]));

        return $this->redirectRoute('workouts.show', $copy, navigate: true);
    }

    public function duplicate(int $id)
    {
        $workout = $this->find($id);
        $copy = $workout->duplicate(__(':name (copy)', ['name' => $workout->name]));

        session()->flash('status', __('Copy of :name created.', ['name' => $workout->name]));

        return $this->redirectRoute('workouts.edit', $copy, navigate: true);
    }

    public function archive(int $id): void
    {
        $workout = $this->find($id);
        $workout->update(['archived_at' => now()]);
        $this->message = __(':name is in the archive.', ['name' => $workout->name]);
        unset($this->workouts, $this->counts);
    }

    public function restore(int $id): void
    {
        $workout = $this->find($id);
        $workout->update(['archived_at' => null]);
        $this->message = __(':name is restored.', ['name' => $workout->name]);
        unset($this->workouts, $this->counts);
    }

    public function delete(int $id): void
    {
        $workout = Workout::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('delete', $workout);

        $workout->delete();
        $this->message = __(':name is deleted.', ['name' => $workout->name]);
        unset($this->workouts, $this->counts);
    }

    #[Computed]
    public function workouts()
    {
        if ($this->showReady) {
            return $this->readyWorkouts();
        }

        return Workout::where('user_id', Auth::id())
            ->when($this->showArchived, fn ($q) => $q->archived(), fn ($q) => $q->active())
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->with(['muscleGroups', 'exercises.exercise'])
            ->withCount('exercises')
            ->orderByDesc($this->showArchived ? 'archived_at' : 'updated_at')
            ->get();
    }

    /** Aantallen voor de tabs */
    #[Computed]
    public function counts(): array
    {
        $workouts = Workout::where('user_id', Auth::id());

        return [
            'active' => (clone $workouts)->active()->count(),
            'ready' => $this->readyQuery()->count(),
            'received' => Auth::user()->openSharedCount(),
            'archived' => $workouts->archived()->count(),
        ];
    }

    /** Openstaande gedeelde workouts, nieuwste eerst */
    #[Computed]
    public function received()
    {
        return SharedWorkout::open()->where('recipient_id', Auth::id())
            ->with(['sender', 'workout.exercises.exercise', 'workout.muscleGroups'])
            ->latest('id')
            ->get();
    }

    /** Ids van standaardworkouts die je al hebt overgenomen (en niet hebt weggegooid) */
    #[Computed]
    public function adopted(): array
    {
        return Workout::where('user_id', Auth::id())->whereNotNull('source_workout_id')->pluck('source_workout_id')->unique()->all();
    }

    /** Filters: alleen lichaamsdelen en materiaal die in de kant-en-klare workouts voorkomen */
    #[Computed]
    public function bodyParts()
    {
        return BodyPart::whereHas('muscleGroups.workouts', fn ($q) => $q->whereIn('workouts.id', $this->readyQuery()->select('id')))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return array<int, string> */
    #[Computed]
    public function equipment(): array
    {
        return Equipment::whereNull('user_id')
            ->whereHas('exercises.workoutExercises', fn ($q) => $q->whereIn('workout_id', $this->readyQuery()->select('id')))
            ->orderBy('sort_order')
            ->get()->mapWithKeys(fn (Equipment $equipment) => [$equipment->id => $equipment->label()])->all();
    }

    private function readyQuery()
    {
        return Workout::whereNull('user_id')->unless(Auth::user()->isAdmin(), fn ($q) => $q->active());
    }

    private function readyWorkouts()
    {
        return $this->readyQuery()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->bodyPart, fn ($q) => $q->whereHas('muscleGroups', fn ($q) => $q->where('body_part_id', $this->bodyPart)))
            ->when($this->equipmentFilter, fn ($q) => $q->whereHas('exercises.exercise', fn ($q) => $q->where('equipment_id', $this->equipmentFilter)))
            ->with(['muscleGroups', 'exercises.exercise.equipment'])
            ->withCount(['exercises', 'copies'])
            ->orderBy('archived_at')
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.workouts.index')->title(__('Workouts'));
    }

    private function find(int $id): Workout
    {
        $workout = Workout::visibleTo(Auth::user())->with('exercises')->findOrFail($id);
        $this->authorize('update', $workout);

        return $workout;
    }
}
