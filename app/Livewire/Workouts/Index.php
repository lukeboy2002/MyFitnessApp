<?php

namespace App\Livewire\Workouts;

use App\Livewire\Concerns\ConfirmsDeletion;
use App\Models\Workout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ConfirmsDeletion;

    #[Url(as: 'zoek', except: '')]
    public string $search = '';

    #[Url(as: 'archief', except: false)]
    public bool $showArchived = false;

    public ?string $message = null;

    protected function deletableModel(): string
    {
        return Workout::class;
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
            'archived' => $workouts->archived()->count(),
        ];
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
