<?php

namespace App\Livewire\Exercises;

use App\Enums\CardioField;
use App\Enums\CardioMetric;
use App\Enums\Equipment;
use App\Enums\ExerciseType;
use App\Livewire\Concerns\HandlesImageUpload;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    use HandlesImageUpload;

    public ?Exercise $exercise = null;

    public string $name = '';

    public string $type = 'strength';

    /** Type niet meer te wijzigen: de oefening zit al in een workout of training */
    #[Locked]
    public bool $typeLocked = false;

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

    public ?string $default_metric = null;  // cardio: weerstand, niveau of snelheid

    public $default_metric_value = null;

    /** Cardio: extra velden op het apparaat */
    public array $cardio_fields = [];

    /** Admin: nieuwe oefening als standaardoefening, voor iedereen zichtbaar (bv. na een aanvraag via Contact) */
    public bool $standard = false;

    public function mount(?Exercise $exercise = null): void
    {
        if (! $exercise?->exists) {
            $this->authorize('create', Exercise::class);

            // Vanuit Admin > Contact: naam van de aangevraagde oefening, als standaardoefening
            $this->name = str(request()->string('name'))->limit(100, '')->value();
            $this->standard = Auth::user()->isAdmin() && request()->boolean('standard');

            return;
        }

        $this->authorize('update', $exercise);
        $this->exercise = $exercise;

        $this->fill($exercise->only('name', 'default_sets', 'default_reps', 'default_weight'));
        $this->default_metric = $exercise->default_metric?->value;
        $this->default_metric_value = filled($exercise->default_metric_value) ? rtrim(rtrim((string) $exercise->default_metric_value, '0'), '.') : null;
        $this->cardio_fields = $exercise->cardio_fields ?? [];
        $this->description = $exercise->description ?? '';
        $this->type = $exercise->type->value;
        $this->typeLocked = $exercise->isTypeLocked();
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
        if ($this->typeLocked) {
            $this->type = $this->exercise->type->value;

            return;
        }

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

        // Ook bij een gemanipuleerd verzoek blijft het type gelijk
        if ($this->exercise && $this->exercise->isTypeLocked()) {
            $this->type = $this->exercise->type->value;
        }

        $type = $this->type;
        $this->default_metric_value = is_string($this->default_metric_value) ? str_replace(',', '.', trim($this->default_metric_value)) : $this->default_metric_value;
        $standard = ! $this->exercise && $this->standard && $user->isAdmin();
        // Een standaardoefening mag alleen standaard-spiergroepen hebben, anders ziet niet iedereen ze
        $visibleGroup = Rule::exists('muscle_groups', 'id')->where(fn ($q) => $standard ? $q->whereNull('user_id') : $q->whereNull('user_id')->orWhere('user_id', $user->id));

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
            'default_metric' => ['nullable', Rule::enum(CardioMetric::class)],
            'default_metric_value' => ['nullable', 'numeric', 'between:0,99.9'],
            'cardio_fields' => ['array'],
            'cardio_fields.*' => [Rule::enum(CardioField::class)],
        ], attributes: [
            'name' => __('name'), 'equipment' => __('equipment'), 'description' => __('description'), 'instructions' => __('instructions'),
            'primary_muscle_group_id' => __('primary muscle group'), 'secondary_muscle_group_ids' => __('secondary muscle groups'),
            'image' => __('image'), 'default_sets' => __('sets'), 'default_reps' => __('reps'), 'default_weight' => __('weight'),
            'default_seconds' => __('time'), 'default_minutes' => __('time'), 'default_metric' => __('setting'), 'default_metric_value' => __('value'),
        ]);

        $type = ExerciseType::from($data['type']);
        $exercise = $this->exercise ?? new Exercise(['user_id' => $standard ? null : $user->id]);

        // Alleen de doelvelden die bij het type horen bewaren
        $exercise->fill([
            'name' => $data['name'],
            'slug' => $exercise->slugFor($data['name']),
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
            'default_metric' => $type->usesMetric() ? $data['default_metric'] : null,
            'default_metric_value' => $type->usesMetric() && filled($data['default_metric_value']) ? $data['default_metric_value'] : null,
            'cardio_fields' => $type === ExerciseType::Cardio && $data['cardio_fields']
                ? collect(CardioField::cases())->pluck('value')->intersect($data['cardio_fields'])->values()->all()
                : null,
        ]);

        $this->applyImage($exercise, 'exercises');
        $exercise->save();
        $exercise->syncMuscleGroups($data['primary_muscle_group_id'], $data['secondary_muscle_group_ids']);

        session()->flash('status', __(':name is saved.', ['name' => $exercise->name]));

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
        ])->title($this->exercise ? __('Edit exercise') : __('New exercise'));
    }

    /** Tekstvak met één item per regel naar een array (of null als het leeg is) */
    private function lines(?string $text): ?array
    {
        $lines = collect(preg_split('/\R/', (string) $text))->map(fn ($line) => trim($line))->filter()->values()->all();

        return $lines ?: null;
    }
}
