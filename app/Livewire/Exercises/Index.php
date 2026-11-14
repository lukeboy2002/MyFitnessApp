<?php

namespace App\Livewire\Exercises;

use App\Enums\ExerciseType;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ConfirmsDeletion;

    /** A t/m Z, plus # voor namen die met een cijfer of teken beginnen */
    public const LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '#'];

    #[Url(as: 'zoek', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: '')]
    public string $typeFilter = '';

    #[Url(as: 'groep')]
    public ?int $groupFilter = null;

    #[Url(as: 'eigen', except: false)]
    public bool $ownOnly = false;

    #[Url(as: 'archief', except: false)]
    public bool $showArchived = false;

    /** Letterbalk (zoals contactpersonen): alleen oefeningen die met deze letter beginnen; '#' = cijfer of teken */
    #[Url(as: 'letter', except: '')]
    public string $letter = '';

    public ?string $message = null;

    public ?string $error = null;

    /** Eigen kopie van een (standaard)oefening, daarna meteen bewerken */
    public function duplicate(int $id)
    {
        $exercise = Exercise::visibleTo(Auth::user())->with('muscleGroups')->findOrFail($id);
        $this->authorize('view', $exercise);
        $this->authorize('create', Exercise::class);

        $copy = $exercise->replicate(['archived_at']);
        $copy->user_id = Auth::id();
        $copy->name = $exercise->isStandard() ? $exercise->name : __(':name (copy)', ['name' => $exercise->name]);
        $copy->slug = Exercise::uniqueSlug($copy->name, Auth::id());

        if ($exercise->image_path) {
            $copy->image_path = 'exercises/'.str()->random(40).'.'.pathinfo($exercise->image_path, PATHINFO_EXTENSION);
            Storage::disk('public')->copy($exercise->image_path, $copy->image_path);
        }

        $copy->save();

        if ($primary = $exercise->primaryMuscleGroup()) {
            $copy->syncMuscleGroups($primary->id, $exercise->secondaryMuscleGroups()->pluck('id')->all());
        }

        session()->flash('status', __('Copy of :name created. Adjust it here.', ['name' => $exercise->name]));

        return $this->redirectRoute('exercises.edit', $copy, navigate: true);
    }

    public function archive(int $id): void
    {
        $exercise = Exercise::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('update', $exercise);

        $exercise->update(['archived_at' => now()]);
        $this->message = __(':name is archived.', ['name' => $exercise->name]);
        unset($this->filtered, $this->exercises, $this->letters);
    }

    public function restore(int $id): void
    {
        $exercise = Exercise::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('update', $exercise);

        $exercise->update(['archived_at' => null]);
        $this->message = __(':name is back in your list.', ['name' => $exercise->name]);
        unset($this->filtered, $this->exercises, $this->letters);
    }

    protected function deletableModel(): string
    {
        return Exercise::class;
    }

    public function delete(int $id): void
    {
        $exercise = Exercise::visibleTo(Auth::user())->withCount('workoutExercises', 'sessionExercises')->findOrFail($id);
        $this->authorize('delete', $exercise);

        if ($exercise->session_exercises_count > 0) {
            $this->message = null;
            $this->error = __('You have trained :name before; deleting it would erase your history. Archive it instead.', ['name' => $exercise->name]);

            return;
        }

        if ($exercise->workout_exercises_count > 0) {
            $this->message = null;
            $this->error = trans_choice(':name is still in :count workout. Take it out there first, or archive it.|:name is still in :count workouts. Take it out there first, or archive it.', $exercise->workout_exercises_count, ['name' => $exercise->name]);

            return;
        }

        if ($exercise->image_path) {
            Storage::disk('public')->delete($exercise->image_path);
        }

        $exercise->delete();
        $this->error = null;
        $this->message = __(':name is deleted.', ['name' => $exercise->name]);
        unset($this->filtered, $this->exercises, $this->letters);
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'typeFilter', 'groupFilter', 'ownOnly', 'letter');
    }

    public function pickLetter(string $letter): void
    {
        $this->letter = $this->letter === $letter || ! in_array($letter, self::LETTERS, true) ? '' : $letter;
    }

    public static function letterOf(string $name): string
    {
        $first = Str::upper(Str::ascii(mb_substr(ltrim($name), 0, 1)));

        return in_array($first, self::LETTERS, true) ? $first : '#';
    }

    #[Computed]
    public function muscleGroups()
    {
        return MuscleGroup::visibleTo(Auth::user())->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Oefeningen na zoeken en filters, nog zonder letter (daaruit volgt welke letters er zijn) */
    #[Computed]
    public function filtered()
    {
        return Exercise::visibleTo(Auth::user())
            ->with('muscleGroups')
            ->when($this->showArchived, fn ($q) => $q->archived(), fn ($q) => $q->active())
            ->when($this->ownOnly && $this->ownCount, fn ($q) => $q->where('user_id', Auth::id()))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when(ExerciseType::tryFrom($this->typeFilter), fn ($q, $type) => $q->where('type', $type))
            ->when($this->groupFilter, fn ($q) => $q->whereHas('muscleGroups', fn ($q) => $q->whereKey($this->groupFilter)))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function exercises()
    {
        return $this->letter === ''
            ? $this->filtered
            : $this->filtered->filter(fn (Exercise $exercise) => self::letterOf($exercise->name) === $this->letter)->values();
    }

    /** Letters waarmee minstens één oefening begint; de rest staat grijs in de letterbalk */
    #[Computed]
    public function letters(): array
    {
        return $this->filtered->map(fn (Exercise $exercise) => self::letterOf($exercise->name))->unique()->values()->all();
    }

    /** Eigen oefeningen (ook gearchiveerd); 0 = filter niet tonen */
    #[Computed]
    public function ownCount(): int
    {
        return Exercise::where('user_id', Auth::id())->count();
    }

    #[Computed]
    public function archivedCount(): int
    {
        return Exercise::where('user_id', Auth::id())->archived()->count();
    }

    public function render()
    {
        return view('livewire.exercises.index')->title(__('Exercises'));
    }
}
