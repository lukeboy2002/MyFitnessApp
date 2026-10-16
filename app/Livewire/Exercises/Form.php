<?php

namespace App\Livewire\Exercises;

use App\Enums\Equipment;
use App\Enums\ExerciseType;
use App\Livewire\Concerns\HandlesImageUpload;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    use HandlesImageUpload;

    public ?Exercise $exercise = null;

    public string $name = '';

    public string $type = 'strength';

    public string $equipment = 'machine';

    public string $description = '';

    /** Uitvoering en tips: één stap per regel */
    public string $instructions = '';

    public string $tips = '';

    public ?int $primary_muscle_group_id = null;

    public array $secondary_muscle_group_ids = [];

    // Standaarddoel
    public ?int $default_sets = 3;

    public ?int $default_reps = 10;

    public $default_weight = null;

    public ?int $default_seconds = null;  // type "tijd"

    public ?int $default_minutes = null;  // cardio

    public ?int $default_intensity = null;

    public function mount(?Exercise $exercise = null): void
    {
        if (! $exercise?->exists) {
            $this->authorize('create', Exercise::class);

            return;
        }

        $this->authorize('update', $exercise);
        $this->exercise = $exercise;

        $this->fill($exercise->only('name', 'default_sets', 'default_reps', 'default_weight', 'default_intensity'));
        $this->description = $exercise->description ?? '';
        $this->type = $exercise->type->value;
        $this->equipment = $exercise->equipment?->value ?? 'none';
        $this->instructions = implode("\n", $exercise->instructions ?? []);
        $this->tips = implode("\n", $exercise->tips ?? []);
        $this->primary_muscle_group_id = $exercise->primaryMuscleGroup()?->id;
        $this->secondary_muscle_group_ids = $exercise->secondaryMuscleGroups()->pluck('id')->all();
        $this->currentImage = $exercise->image_path ? $exercise->imageUrl() : null;

        $seconds = $exercise->default_duration_seconds;
        $exercise->type === ExerciseType::Cardio
            ? $this->default_minutes = $seconds ? intdiv($seconds, 60) : null
            : $this->default_seconds = $seconds;
    }

    /** Bij een ander type: lege doelvelden vullen met de standaard van dat type */
    public function updatedType(string $value): void
    {
        $type = ExerciseType::tryFrom($value) ?? ExerciseType::Strength;
        $defaults = $type->defaults();

        $this->default_sets ??= $defaults['default_sets'] ?? null;
        $this->default_reps ??= $defaults['default_reps'] ?? null;

        if ($type === ExerciseType::Cardio) {
            $this->default_minutes ??= intdiv($defaults['default_duration_seconds'], 60);
            $this->equipment = $this->equipment === 'machine' ? 'cardio_machine' : $this->equipment;
            $this->primary_muscle_group_id ??= $this->muscleGroups->firstWhere('slug', 'cardio')?->id;
        } elseif ($type === ExerciseType::Duration) {
            $this->default_seconds ??= $defaults['default_duration_seconds'];
        }
    }

    /** De primaire spiergroep hoort niet ook bij de secundaire */
    public function updatedPrimaryMuscleGroupId(): void
    {
        $this->secondary_muscle_group_ids = array_values(array_diff($this->secondary_muscle_group_ids, [$this->primary_muscle_group_id]));
    }

    public function save()
    {
        $user = Auth::user();
        $type = $this->type;
        $visibleGroup = Rule::exists('muscle_groups', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(ExerciseType::class)],
            'equipment' => ['required', Rule::enum(Equipment::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'tips' => ['nullable', 'string', 'max:2000'],
            'primary_muscle_group_id' => ['required', 'integer', $visibleGroup],
            'secondary_muscle_group_ids' => ['array', 'max:8'],
            'secondary_muscle_group_ids.*' => ['integer', $visibleGroup],
            ...$this->imageRules(),
            'default_sets' => ['nullable', 'integer', 'between:1,20'],
            'default_reps' => ['nullable', 'integer', 'between:1,200'],
            'default_weight' => ['nullable', 'numeric', $type === 'bodyweight' ? 'between:-200,200' : 'between:0,999'],
            'default_seconds' => ['nullable', 'integer', 'between:1,3600'],
            'default_minutes' => ['nullable', 'integer', 'between:1,600'],
            'default_intensity' => ['nullable', 'integer', 'between:1,30'],
        ], attributes: [
            'name' => 'naam', 'equipment' => 'materiaal', 'description' => 'omschrijving', 'instructions' => 'uitvoering',
            'primary_muscle_group_id' => 'primaire spiergroep', 'secondary_muscle_group_ids' => 'secundaire spiergroepen',
            'image' => 'plaatje', 'default_sets' => 'sets', 'default_reps' => 'herhalingen', 'default_weight' => 'gewicht',
            'default_seconds' => 'tijd', 'default_minutes' => 'tijd', 'default_intensity' => 'intensiteit',
        ]);

        $type = ExerciseType::from($data['type']);
        $exercise = $this->exercise ?? new Exercise(['user_id' => $user->id]);

        // Alleen de doelvelden die bij het type horen bewaren
        $exercise->fill([
            'name' => $data['name'],
            'slug' => Exercise::uniqueSlug($data['name'], $user->id, $exercise->id),
            'type' => $type,
            'equipment' => $data['equipment'],
            'description' => $data['description'] ?: null,
            'instructions' => $this->lines($data['instructions']),
            'tips' => $this->lines($data['tips']),
            'default_sets' => $type->usesSets() ? $data['default_sets'] : null,
            'default_reps' => $type->usesReps() ? $data['default_reps'] : null,
            'default_weight' => ($type->usesWeight() || $type->usesExtraWeight()) && filled($data['default_weight']) ? $data['default_weight'] : null,
            'default_duration_seconds' => match ($type) {
                ExerciseType::Cardio => $data['default_minutes'] ? $data['default_minutes'] * 60 : null,
                ExerciseType::Duration => $data['default_seconds'],
                default => null,
            },
            'default_intensity' => $type->usesIntensity() ? $data['default_intensity'] : null,
        ]);

        $this->applyImage($exercise, 'exercises');
        $exercise->save();
        $exercise->syncMuscleGroups($data['primary_muscle_group_id'], $data['secondary_muscle_group_ids']);

        session()->flash('status', "{$exercise->name} is opgeslagen.");

        return $this->redirectRoute('exercises.show', $exercise, navigate: true);
    }

    #[Computed]
    public function muscleGroups()
    {
        return MuscleGroup::visibleTo(Auth::user())->with('bodyPart')->orderBy('sort_order')->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.exercises.form', [
            'exerciseType' => ExerciseType::tryFrom($this->type) ?? ExerciseType::Strength,
        ])->title($this->exercise ? 'Oefening bewerken' : 'Nieuwe oefening');
    }

    /** Tekstvak met één item per regel naar een array (of null als het leeg is) */
    private function lines(?string $text): ?array
    {
        $lines = collect(preg_split('/\R/', (string) $text))->map(fn ($line) => trim($line))->filter()->values()->all();

        return $lines ?: null;
    }
}
