<?php

namespace App\Livewire\Exercises;

use App\Enums\ExerciseType;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Exercises')]
class Index extends Component
{
    use ConfirmsDeletion;

    #[Url(as: 'zoek', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: '')]
    public string $typeFilter = '';

    #[Url(as: 'groep')]
    public ?int $groupFilter = null;

    #[Url(as: 'archief', except: false)]
    public bool $showArchived = false;

    public ?string $message = null;

    public function mount(): void
    {
        $this->message = session('status');
    }

    /** Eigen kopie van een (standaard)oefening, daarna meteen bewerken */
    public function duplicate(int $id)
    {
        $exercise = Exercise::visibleTo(Auth::user())->with('muscleGroups')->findOrFail($id);
        $this->authorize('view', $exercise);
        $this->authorize('create', Exercise::class);

        $copy = $exercise->replicate(['archived_at']);
        $copy->user_id = Auth::id();
        $copy->name = $exercise->isStandard() ? $exercise->name : $exercise->name.' (kopie)';
        $copy->slug = Exercise::uniqueSlug($copy->name, Auth::id());

        if ($exercise->image_path) {
            $copy->image_path = 'exercises/'.str()->random(40).'.'.pathinfo($exercise->image_path, PATHINFO_EXTENSION);
            Storage::disk('public')->copy($exercise->image_path, $copy->image_path);
        }

        $copy->save();

        if ($primary = $exercise->primaryMuscleGroup()) {
            $copy->syncMuscleGroups($primary->id, $exercise->secondaryMuscleGroups()->pluck('id')->all());
        }

        session()->flash('status', "Kopie van {$exercise->name} gemaakt. Pas hem hier aan.");

        return $this->redirectRoute('exercises.edit', $copy, navigate: true);
    }

    public function archive(int $id): void
    {
        $exercise = Exercise::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('update', $exercise);

        $exercise->update(['archived_at' => now()]);
        $this->message = "{$exercise->name} is gearchiveerd.";
        unset($this->exercises);
    }

    public function restore(int $id): void
    {
        $exercise = Exercise::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('update', $exercise);

        $exercise->update(['archived_at' => null]);
        $this->message = "{$exercise->name} staat weer in je lijst.";
        unset($this->exercises);
    }

    protected function deletableModel(): string
    {
        return Exercise::class;
    }

    public function delete(int $id): void
    {
        $exercise = Exercise::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('delete', $exercise);

        if ($exercise->image_path) {
            Storage::disk('public')->delete($exercise->image_path);
        }

        $exercise->delete();
        $this->message = "{$exercise->name} is verwijderd.";
        unset($this->exercises);
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'typeFilter', 'groupFilter');
    }

    #[Computed]
    public function muscleGroups()
    {
        return MuscleGroup::visibleTo(Auth::user())->orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function exercises()
    {
        return Exercise::visibleTo(Auth::user())
            ->with('muscleGroups')
            ->when($this->showArchived, fn ($q) => $q->archived(), fn ($q) => $q->active())
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when(ExerciseType::tryFrom($this->typeFilter), fn ($q, $type) => $q->where('type', $type))
            ->when($this->groupFilter, fn ($q) => $q->whereHas('muscleGroups', fn ($q) => $q->whereKey($this->groupFilter)))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function archivedCount(): int
    {
        return Exercise::where('user_id', Auth::id())->archived()->count();
    }

    public function render()
    {
        return view('livewire.exercises.index');
    }
}
