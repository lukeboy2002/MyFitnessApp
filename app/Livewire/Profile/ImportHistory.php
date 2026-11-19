<?php

namespace App\Livewire\Profile;

use App\Actions\ImportWorkoutHistory;
use App\Enums\ExerciseType;
use App\Livewire\Concerns\PicksExercises;
use App\Models\DataImport;
use App\Models\Exercise;
use App\Models\WorkoutSession;
use App\Support\Achievements;
use App\Support\Import\ExerciseMatcher;
use App\Support\Import\WorkoutFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Profiel > Account: je geschiedenis uit Hevy, Strong of FitNotes meenemen.
 * Stap 1 bestand kiezen, stap 2 controleren en oefeningen koppelen, stap 3 klaar (ongedaan maken kan altijd).
 * Het bestand staat tot het importeren in storage/app/private/imports.
 */
class ImportHistory extends Component
{
    use PicksExercises, WithFileUploads;

    public $file = null;

    #[Locked]
    public ?string $stored = null;

    #[Locked]
    public string $filename = '';

    /** Naam in het bestand => exercise_id; 0 = overslaan, null = nog niet gekozen */
    #[Locked]
    public array $map = [];

    /** Namen die vanzelf gekoppeld zijn */
    #[Locked]
    public array $sure = [];

    public bool $imperial = false;

    #[Locked]
    public ?string $pickingFor = null;

    #[Locked]
    public ?array $result = null;

    public bool $confirmingUndo = false;

    #[Locked]
    public ?int $undoingId = null;

    public function updatedFile(): void
    {
        $this->validate(['file' => ['required', 'file', 'extensions:csv,txt', 'max:20480']]);

        $this->discard();
        $path = $this->file->storeAs('imports', Str::uuid().'.csv', 'local');
        $filename = $this->file->getClientOriginalName();
        $this->reset('file', 'result');

        try {
            $parsed = WorkoutFile::read(Storage::disk('local')->path($path));
        } catch (RuntimeException) {
            Storage::disk('local')->delete($path);
            $this->addError('file', __('We do not recognise this file. Choose the CSV export from Hevy, Strong or FitNotes.'));

            return;
        }

        if (! $parsed->setCount()) {
            Storage::disk('local')->delete($path);
            $this->addError('file', __('There are no trainings in this file.'));

            return;
        }

        $this->stored = $path;
        $this->filename = $filename;

        $matcher = new ExerciseMatcher(Auth::user());
        foreach (array_keys($parsed->exercises()) as $name) {
            $match = $matcher->match($name);
            $this->map[$name] = $match['id'];
            if ($match['sure']) {
                $this->sure[] = $name;
            }
        }
    }

    #[Computed]
    public function workoutFile(): ?WorkoutFile
    {
        try {
            return $this->stored ? WorkoutFile::read(Storage::disk('local')->path($this->stored)) : null;
        } catch (RuntimeException) {
            return null;
        }
    }

    /** Wat er in het bestand staat */
    #[Computed]
    public function preview(): array
    {
        $file = $this->workoutFile;
        $sessions = collect($file->sessions($this->imperial));
        $ids = $sessions->map(fn ($session) => ImportWorkoutHistory::externalId($file->app, $session));
        $existing = WorkoutSession::where('user_id', Auth::id())->whereIn('external_id', $ids)->count();

        return [
            'trainings' => $sessions->count(),
            'new' => $sessions->count() - $existing,
            'existing' => $existing,
            'sets' => $file->setCount(),
            'from' => $sessions->min('started_at'),
            'until' => $sessions->max('started_at'),
            'exercises' => $file->exercises(),
        ];
    }

    /** Gekozen oefeningen: [id => naam] */
    #[Computed]
    public function chosen(): array
    {
        return Exercise::visibleTo(Auth::user())->whereKey(array_filter($this->map))->pluck('name', 'id')->all();
    }

    /** Eerdere imports, om ongedaan te maken */
    #[Computed]
    public function imports()
    {
        return DataImport::where('user_id', Auth::id())->latest()->get();
    }

    public function choose(string $name): void
    {
        abort_unless(array_key_exists($name, $this->map), 404);

        $this->pickingFor = $name;
        $this->openPicker();
        $this->pickerSearch = Str::before(Str::before($name, '('), ' ');
    }

    public function pick(int $id): void
    {
        abort_unless($this->pickingFor !== null, 404);
        $exercise = Exercise::visibleTo(Auth::user())->findOrFail($id);

        $this->map[$this->pickingFor] = $exercise->id;
        $this->sure = array_values(array_unique([...$this->sure, $this->pickingFor]));
        $this->reset('pickingFor', 'showPicker');
    }

    /** Eigen oefening maken met dezelfde naam; het type volgt uit wat er in het bestand is bijgehouden */
    public function createOwn(string $name): void
    {
        $tracked = $this->preview['exercises'][$name] ?? abort(404);

        $type = match (true) {
            $tracked['distance'] => ExerciseType::Cardio,
            $tracked['weight'] => ExerciseType::Strength,
            $tracked['reps'] => ExerciseType::Bodyweight,
            $tracked['seconds'] => ExerciseType::Duration,
            default => ExerciseType::Strength,
        };

        $exercise = Exercise::visibleTo(Auth::user())->active()->where('user_id', Auth::id())->where('name', $name)->first()
            ?? Exercise::create([
                'user_id' => Auth::id(),
                'name' => $name,
                'slug' => Exercise::uniqueSlug($name, Auth::id()),
                'type' => $type,
            ]);

        $this->map[$name] = $exercise->id;
        $this->sure = array_values(array_unique([...$this->sure, $name]));
        unset($this->chosen);
    }

    public function skip(string $name): void
    {
        abort_unless(array_key_exists($name, $this->map), 404);

        $this->map[$name] = 0;
        $this->sure = array_values(array_diff($this->sure, [$name]));
    }

    public function import(ImportWorkoutHistory $action): void
    {
        $file = $this->workoutFile ?? abort(404);
        $user = Auth::user();

        $before = Achievements::for($user)->sum(fn ($badge) => $badge->level());
        $import = $action->run($user, $file, array_map(fn ($id) => (int) $id, $this->map), $this->filename, $this->imperial);
        $badges = Achievements::for($user->fresh())->sum(fn ($badge) => $badge->level()) - $before;

        $this->discard();
        $this->result = ['created' => $import->sessions_count, 'skipped' => $import->skipped_count, 'badges' => max(0, $badges)];
        unset($this->imports);
    }

    public function cancel(): void
    {
        $this->discard();
        $this->reset('result');
    }

    public function confirmUndo(int $id): void
    {
        $this->undoingId = DataImport::where('user_id', Auth::id())->findOrFail($id)->id;
        $this->confirmingUndo = true;
    }

    public function undo(ImportWorkoutHistory $action): void
    {
        $import = DataImport::where('user_id', Auth::id())->findOrFail($this->undoingId);
        $action->undo($import);

        $this->reset('confirmingUndo', 'undoingId', 'result');
        unset($this->imports);
    }

    /** Geüpload bestand en keuzes weg */
    private function discard(): void
    {
        if ($this->stored) {
            Storage::disk('local')->delete($this->stored);
        }
        $this->reset('stored', 'filename', 'map', 'sure', 'imperial', 'pickingFor');
        unset($this->workoutFile, $this->preview, $this->chosen);
    }

    public function render()
    {
        return view('livewire.profile.import-history');
    }
}
