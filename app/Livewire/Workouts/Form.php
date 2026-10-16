<?php

namespace App\Livewire\Workouts;

use App\Enums\CardioMetric;
use App\Enums\ExerciseType;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?Workout $workout = null;

    public string $name = '';

    public string $color = Workout::COLORS[0];

    public string $description = '';

    public ?int $estimated_minutes = null;

    public array $muscle_group_ids = [];

    /**
     * De oefeningen met hun doelen. Per regel: key, id (workout_exercises.id of null), exercise_id,
     * sets, reps, weight, rest, seconds (type tijd), minutes + metric + metric_value (cardio), notes.
     */
    public array $items = [];

    // Oefening kiezen
    public bool $showPicker = false;

    public string $pickerSearch = '';

    public string $pickerType = '';

    public ?int $pickerGroup = null;

    public function mount(?Workout $workout = null): void
    {
        if (! $workout?->exists) {
            $this->authorize('create', Workout::class);

            return;
        }

        $this->authorize('update', $workout);
        $this->workout = $workout;

        $this->name = $workout->name;
        $this->color = $workout->color ?? Workout::COLORS[0];
        $this->description = $workout->description ?? '';
        $this->estimated_minutes = $workout->estimated_minutes;
        $this->muscle_group_ids = $workout->muscleGroups()->pluck('muscle_groups.id')->all();
        $this->items = $workout->exercises()->with('exercise')->get()->map(fn (WorkoutExercise $item) => $this->toItem($item->exercise, $item->toArray(), $item->id))->all();
    }

    public function openPicker(): void
    {
        $this->reset('pickerSearch', 'pickerType', 'pickerGroup');
        $this->showPicker = true;
    }

    /** Oefening onderaan toevoegen met het standaarddoel van de oefening; de kiezer blijft open */
    public function addExercise(int $id): void
    {
        $exercise = Exercise::visibleTo(Auth::user())->active()->findOrFail($id);

        $this->items[] = $this->toItem($exercise, WorkoutExercise::defaultsFor($exercise));
        $this->resetErrorBag('items');
        unset($this->itemExercises);
    }

    public function removeItem(string $key): void
    {
        $this->items = array_values(array_filter($this->items, fn ($item) => $item['key'] !== $key));
        unset($this->itemExercises);
    }

    /** wire:sort: regel $key naar plek $position slepen */
    public function sortItem(string $key, int $position): void
    {
        $index = collect($this->items)->search(fn ($item) => $item['key'] === $key);

        if ($index === false) {
            return;
        }

        $item = array_splice($this->items, $index, 1)[0];
        array_splice($this->items, max(0, min($position, count($this->items))), 0, [$item]);
    }

    /** Spiergroepen overnemen van de primaire spiergroepen van de oefeningen */
    public function fillMuscleGroups(): void
    {
        $this->muscle_group_ids = $this->primaryGroupIds();
    }

    public function save()
    {
        $user = Auth::user();

        // Komma's als decimaalteken toestaan (22,5 kg)
        foreach ($this->items as $i => $item) {
            foreach (['weight', 'metric_value'] as $field) {
                if (is_string($item[$field] ?? null)) {
                    $this->items[$i][$field] = str_replace(',', '.', trim($item[$field]));
                }
            }
        }

        $visible = fn (string $table) => Rule::exists($table, 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', Rule::in(Workout::COLORS)],
            'description' => ['nullable', 'string', 'max:2000'],
            'estimated_minutes' => ['nullable', 'integer', 'between:1,600'],
            'muscle_group_ids' => ['array', 'max:12'],
            'muscle_group_ids.*' => ['integer', $visible('muscle_groups')],
            'items' => ['array', 'min:1', 'max:40'],
            'items.*.exercise_id' => ['required', 'integer', $visible('exercises')],
            'items.*.sets' => ['nullable', 'integer', 'between:1,20'],
            'items.*.reps' => ['nullable', 'integer', 'between:1,200'],
            'items.*.weight' => ['nullable', 'numeric', 'between:-200,999'],
            'items.*.rest' => ['nullable', 'integer', 'between:0,900'],
            'items.*.seconds' => ['nullable', 'integer', 'between:1,3600'],
            'items.*.minutes' => ['nullable', 'integer', 'between:1,600'],
            'items.*.metric' => ['nullable', Rule::enum(CardioMetric::class)],
            'items.*.metric_value' => ['nullable', 'numeric', 'between:0,99.9'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ], [
            'items.min' => 'Voeg minstens één oefening toe.',
        ], [
            'name' => 'naam', 'color' => 'kleur', 'description' => 'omschrijving', 'estimated_minutes' => 'duur',
            'muscle_group_ids' => 'spiergroepen', 'items' => 'oefeningen', 'items.*.sets' => 'sets', 'items.*.reps' => 'herhalingen',
            'items.*.weight' => 'gewicht', 'items.*.rest' => 'rust', 'items.*.seconds' => 'tijd', 'items.*.minutes' => 'tijd',
            'items.*.metric' => 'instelling', 'items.*.metric_value' => 'waarde', 'items.*.notes' => 'notitie',
        ]);

        $exercises = Exercise::whereIn('id', Arr::pluck($data['items'], 'exercise_id'))->get()->keyBy('id');
        $workout = $this->workout ?? new Workout(['user_id' => $user->id]);

        DB::transaction(function () use ($workout, $data, $exercises, $user) {
            $workout->fill([
                'name' => $data['name'],
                'slug' => Workout::uniqueSlug($data['name'], $user->id, $workout->id),
                'color' => $data['color'],
                'description' => $data['description'] ?: null,
                'estimated_minutes' => $data['estimated_minutes'],
            ])->save();

            // Bestaande regels bijwerken (id blijft gelijk), nieuwe aanmaken, weggehaalde verwijderen
            $keep = [];
            foreach (array_values($data['items']) as $position => $item) {
                $attributes = ['position' => $position, ...$this->targets($exercises[$item['exercise_id']]->type, $item)];
                $row = filled($this->items[$position]['id'] ?? null)
                    ? $workout->exercises()->find($this->items[$position]['id'])
                    : null;

                $row
                    ? $row->update(['exercise_id' => $item['exercise_id'], ...$attributes])
                    : $row = $workout->exercises()->create(['exercise_id' => $item['exercise_id'], ...$attributes]);

                $keep[] = $row->id;
            }
            $workout->exercises()->whereNotIn('id', $keep)->delete();

            $workout->muscleGroups()->sync($data['muscle_group_ids'] ?: $this->primaryGroupIds());
        });

        session()->flash('status', "{$workout->name} is opgeslagen.");

        return $this->redirectRoute('workouts.show', $workout, navigate: true);
    }

    #[Computed]
    public function muscleGroups()
    {
        return MuscleGroup::visibleTo(Auth::user())->orderBy('sort_order')->orderBy('name')->get();
    }

    /** De oefeningen die nu in de lijst staan, op id */
    #[Computed]
    public function itemExercises()
    {
        return Exercise::whereIn('id', Arr::pluck($this->items, 'exercise_id'))->with('muscleGroups')->get()->keyBy('id');
    }

    #[Computed]
    public function pickerExercises()
    {
        return Exercise::visibleTo(Auth::user())->active()
            ->when($this->pickerSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->pickerSearch.'%'))
            ->when($this->pickerType !== '', fn ($q) => $q->where('type', $this->pickerType))
            ->when($this->pickerGroup, fn ($q) => $q->whereHas('muscleGroups', fn ($q) => $q->whereKey($this->pickerGroup)))
            ->with('muscleGroups')
            ->orderByRaw('user_id is null') // eigen oefeningen eerst
            ->orderBy('name')
            ->limit(40)
            ->get();
    }

    public function render()
    {
        return view('livewire.workouts.form')->title($this->workout ? 'Workout bewerken' : 'Nieuwe workout');
    }

    /** Doelvelden van een regel naar de database-kolommen; alleen wat bij het type hoort */
    private function targets(ExerciseType $type, array $item): array
    {
        $cardio = $type === ExerciseType::Cardio;

        return [
            'target_sets' => $type->usesSets() ? $item['sets'] : null,
            'target_reps' => $type->usesReps() ? $item['reps'] : null,
            'target_weight' => ($type->usesWeight() || $type->usesExtraWeight()) && filled($item['weight']) ? $item['weight'] : null,
            'rest_seconds' => $type->usesSets() ? $item['rest'] : null,
            'target_duration_seconds' => match (true) {
                $cardio => $item['minutes'] ? $item['minutes'] * 60 : null,
                $type === ExerciseType::Duration => $item['seconds'],
                default => null,
            },
            'target_metric' => $cardio ? ($item['metric'] ?: null) : null,
            'target_metric_value' => $cardio && filled($item['metric_value']) ? $item['metric_value'] : null,
            'notes' => filled($item['notes']) ? $item['notes'] : null,
        ];
    }

    /** Een regel voor het formulier, uit de kolommen van workout_exercises */
    private function toItem(Exercise $exercise, array $targets, ?int $id = null): array
    {
        $seconds = $targets['target_duration_seconds'] ?? null;
        $cardio = $exercise->type === ExerciseType::Cardio;
        $weight = $targets['target_weight'] ?? null;
        $metric = $targets['target_metric'] ?? null;
        $metricValue = $targets['target_metric_value'] ?? null;

        return [
            'key' => (string) Str::uuid(),
            'id' => $id,
            'exercise_id' => $exercise->id,
            'sets' => $targets['target_sets'] ?? null,
            'reps' => $targets['target_reps'] ?? null,
            'weight' => filled($weight) ? rtrim(rtrim((string) $weight, '0'), '.') : null,
            'rest' => $targets['rest_seconds'] ?? null,
            'seconds' => $cardio ? null : $seconds,
            'minutes' => $cardio && $seconds ? intdiv($seconds, 60) : null,
            'metric' => $metric instanceof CardioMetric ? $metric->value : $metric,
            'metric_value' => filled($metricValue) ? rtrim(rtrim((string) $metricValue, '0'), '.') : null,
            'notes' => $targets['notes'] ?? null,
        ];
    }

    /** @return list<int> */
    private function primaryGroupIds(): array
    {
        return $this->itemExercises->map(fn (Exercise $exercise) => $exercise->primaryMuscleGroup()?->id)->filter()->unique()->values()->all();
    }
}
