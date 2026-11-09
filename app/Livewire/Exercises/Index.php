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
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Exercises')]
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
        unset($this->filtered, $this->exercises, $this->letters);
    }

    public function restore(int $id): void
    {
        $exercise = Exercise::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('update', $exercise);

        $exercise->update(['archived_at' => null]);
        $this->message = "{$exercise->name} staat weer in je lijst.";
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
            $this->error = "{$exercise->name} heb je al eens getraind; verwijderen zou je historie wissen. Archiveer hem liever.";

            return;
        }

        if ($exercise->workout_exercises_count > 0) {
            $this->message = null;
            $this->error = "{$exercise->name} zit nog in {$exercise->workout_exercises_count} workout(s). Haal hem daar eerst weg, of archiveer hem.";

            return;
        }

        if ($exercise->image_path) {
            Storage::disk('public')->delete($exercise->image_path);
        }

        $exercise->delete();
        $this->error = null;
        $this->message = "{$exercise->name} is verwijderd.";
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
        return view('livewire.exercises.index');
    }
}
