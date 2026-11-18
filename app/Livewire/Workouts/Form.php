<?php

namespace App\Livewire\Workouts;

use App\Enums\ExerciseType;
use App\Livewire\Concerns\EditsExerciseTargets;
use App\Livewire\Concerns\PicksExercises;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Support\Supersets;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    use EditsExerciseTargets, PicksExercises;

    public ?Workout $workout = null;

    /** Kant-en-klare workout (user_id null): alleen de admin, alleen standaardoefeningen en -spiergroepen */
    #[Locked]
    public bool $standard = false;

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

    public function mount(?Workout $workout = null): void
    {
        if (! $workout?->exists) {
            $this->authorize('create', Workout::class);
            $this->standard = request()->boolean('standaard') && Auth::user()->can('createStandard', Workout::class);

            return;
        }

        $this->authorize('update', $workout);
        $this->workout = $workout;
        $this->standard = $workout->isStandard();

        $this->name = $workout->name;
        $this->color = $workout->color ?? Workout::COLORS[0];
        $this->description = $workout->description ?? '';
        $this->estimated_minutes = $workout->estimated_minutes;
        $this->muscle_group_ids = $workout->muscleGroups()->pluck('muscle_groups.id')->all();
        $this->items = $workout->exercises()->with('exercise')->get()->map(fn (WorkoutExercise $item) => $this->toItem($item->exercise, $item->toArray(), $item->id))->all();
    }

    /** Oefening onderaan toevoegen met het standaarddoel van de oefening; de kiezer blijft open */
    public function addExercise(int $id): void
    {
        $exercise = Exercise::visibleTo($this->pickerUser())->active()->findOrFail($id);

        $this->items[] = $this->toItem($exercise, WorkoutExercise::defaultsFor($exercise, Auth::user()));
        $this->resetErrorBag('items');
        unset($this->itemExercises);
    }

    public function removeItem(string $key): void
    {
        $this->items = array_values(array_filter($this->items, fn ($item) => $item['key'] !== $key));
        unset($this->itemExercises);
        $this->normalizeSupersets();
    }

    /** Superset: regel $key koppelen aan de volgende, of het paar weer losmaken */
    public function toggleSuperset(string $key): void
    {
        [$order, $linked, $cardio] = $this->supersetState();
        $first = Supersets::block($order, $linked, $key)[0];

        if ($linked[$first]) {
            $linked[$first] = false;
        } elseif (Supersets::canLink($order, $linked, $cardio, $key)) {
            $linked[$key] = true;
        }

        $this->items = array_map(fn (array $item) => ['superset' => $linked[$item['key']]] + $item, $this->items);
        $this->normalizeSupersets();
    }

    /** wire:sort: regel $key naar plek $position slepen */
    public function sortItem(string $key, int $position): void
    {
        [$order, $linked] = $this->supersetState();

        if (! in_array($key, $order, true)) {
            return;
        }

        // Een superset-paar gaat samen mee
        $items = collect($this->items)->keyBy('key');
        $this->items = array_map(fn ($k) => $items[$k], Supersets::move($order, $linked, $key, $position));
        $this->normalizeSupersets();
    }

    /** Spiergroepen overnemen van de primaire spiergroepen van de oefeningen */
    public function fillMuscleGroups(): void
    {
        $this->muscle_group_ids = $this->primaryGroupIds();
    }

    public function save()
    {
        $user = Auth::user();

        $this->items = array_map(fn (array $item) => $this->normalizeDecimals($item), $this->items);

        $visible = fn (string $table) => Rule::exists($table, 'id')->where(fn ($q) => $this->standard ? $q->whereNull('user_id') : $q->whereNull('user_id')->orWhere('user_id', $user->id));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', Rule::in(Workout::COLORS)],
            'description' => ['nullable', 'string', 'max:2000'],
            'estimated_minutes' => ['nullable', 'integer', 'between:1,600'],
            'muscle_group_ids' => ['array', 'max:12'],
            'muscle_group_ids.*' => ['integer', $visible('muscle_groups')],
            'items' => ['array', 'min:1', 'max:40'],
            'items.*.exercise_id' => ['required', 'integer', $visible('exercises')],
            ...$this->targetRules('items.*'),
        ], [
            'items.min' => __('Add at least one exercise.'),
        ], [
            'name' => 'naam', 'color' => 'kleur', 'description' => 'omschrijving', 'estimated_minutes' => 'duur',
            'muscle_group_ids' => 'spiergroepen', 'items' => 'oefeningen', ...$this->targetAttributes('items.*'),
        ]);

        $exercises = Exercise::whereIn('id', Arr::pluck($data['items'], 'exercise_id'))->get()->keyBy('id');
        $workout = $this->workout ?? new Workout(['user_id' => $this->standard ? null : $user->id]);

        DB::transaction(function () use ($workout, $data, $exercises) {
            $workout->fill([
                'name' => $data['name'],
                'slug' => $workout->exists ? $workout->slugFor($data['name']) : Workout::uniqueSlug($data['name'], $workout->user_id),
                'color' => $data['color'],
                'description' => $data['description'] ?: null,
                'estimated_minutes' => $data['estimated_minutes'],
            ])->save();

            // Bestaande regels bijwerken (id blijft gelijk), nieuwe aanmaken, weggehaalde verwijderen
            $keep = [];
            foreach (array_values($data['items']) as $position => $item) {
                $attributes = [
                    'position' => $position,
                    'superset_next' => (bool) ($this->items[$position]['superset'] ?? false),
                    ...$this->targets($exercises[$item['exercise_id']]->type, $item),
                ];
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

        session()->flash('status', __(':name is saved.', ['name' => $workout->name]));

        return $this->redirectRoute('workouts.show', $workout, navigate: true);
    }

    /** De oefeningen die nu in de lijst staan, op id */
    #[Computed]
    public function itemExercises()
    {
        return Exercise::whereIn('id', Arr::pluck($this->items, 'exercise_id'))->with('muscleGroups')->get()->keyBy('id');
    }

    /** Per regel: kan hij aan de volgende gekoppeld worden? (voor het knopje "Superset met volgende") */
    public function linkable(): array
    {
        [$order, $linked, $cardio] = $this->supersetState();

        return collect($order)->mapWithKeys(fn ($key) => [$key => Supersets::canLink($order, $linked, $cardio, $key)])->all();
    }

    public function render()
    {
        return view('livewire.workouts.form')->title(match (true) {
            $this->workout !== null => __('Edit workout'),
            $this->standard => __('New ready-made workout'),
            default => __('New workout'),
        });
    }

    protected function pickerUser(): ?User
    {
        return $this->standard ? null : Auth::user();
    }

    /** @return array{0: list<string>, 1: array<string, bool>, 2: array<string, bool>} volgorde, gekoppeld, cardio */
    private function supersetState(): array
    {
        $items = collect($this->items);

        return [
            $items->pluck('key')->all(),
            $items->mapWithKeys(fn ($item) => [$item['key'] => (bool) ($item['superset'] ?? false)])->all(),
            $items->mapWithKeys(fn ($item) => [$item['key'] => $this->itemExercises[$item['exercise_id']]?->type === ExerciseType::Cardio])->all(),
        ];
    }

    /** Ongeldige koppelingen weghalen (na verwijderen of verslepen, of als cardio ertussen komt) */
    private function normalizeSupersets(): void
    {
        $valid = Supersets::normalize(...$this->supersetState());
        $this->items = array_map(fn (array $item) => ['superset' => $valid[$item['key']]] + $item, $this->items);
    }

    /** @return list<int> */
    private function primaryGroupIds(): array
    {
        return $this->itemExercises->map(fn (Exercise $exercise) => $exercise->primaryMuscleGroup()?->id)->filter()->unique()->values()->all();
    }
}
