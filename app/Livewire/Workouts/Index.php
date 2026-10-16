<?php

namespace App\Livewire\Workouts;

use App\Livewire\Concerns\ConfirmsDeletion;
use App\Models\Workout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Workouts')]
class Index extends Component
{
    use ConfirmsDeletion;

    #[Url(as: 'zoek', except: '')]
    public string $search = '';

    #[Url(as: 'archief', except: false)]
    public bool $showArchived = false;

    public ?string $message = null;

    public function mount(): void
    {
        $this->message = session('status');
    }

    protected function deletableModel(): string
    {
        return Workout::class;
    }

    public function duplicate(int $id)
    {
        $workout = $this->find($id);
        $copy = $workout->duplicate("{$workout->name} (kopie)");

        session()->flash('status', "Kopie van {$workout->name} gemaakt.");

        return $this->redirectRoute('workouts.edit', $copy, navigate: true);
    }

    public function archive(int $id): void
    {
        $workout = $this->find($id);
        $workout->update(['archived_at' => now()]);
        $this->message = "{$workout->name} staat in het archief.";
        unset($this->workouts, $this->counts);
    }

    public function restore(int $id): void
    {
        $workout = $this->find($id);
        $workout->update(['archived_at' => null]);
        $this->message = "{$workout->name} is teruggezet.";
        unset($this->workouts, $this->counts);
    }

    public function delete(int $id): void
    {
        $workout = Workout::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('delete', $workout);

        $workout->delete();
        $this->message = "{$workout->name} is verwijderd.";
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
        return view('livewire.workouts.index');
    }

    private function find(int $id): Workout
    {
        $workout = Workout::visibleTo(Auth::user())->with('exercises')->findOrFail($id);
        $this->authorize('update', $workout);

        return $workout;
    }
}
