<?php

namespace App\Livewire\Workouts;

use App\Livewire\Concerns\EditsExerciseTargets;
use App\Livewire\Concerns\PicksExercises;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    use EditsExerciseTargets, PicksExercises;

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

        $this->items = array_map(fn (array $item) => $this->normalizeDecimals($item), $this->items);

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
            ...$this->targetRules('items.*'),
        ], [
            'items.min' => 'Voeg minstens één oefening toe.',
        ], [
            'name' => 'naam', 'color' => 'kleur', 'description' => 'omschrijving', 'estimated_minutes' => 'duur',
            'muscle_group_ids' => 'spiergroepen', 'items' => 'oefeningen', ...$this->targetAttributes('items.*'),
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

    /** De oefeningen die nu in de lijst staan, op id */
    #[Computed]
    public function itemExercises()
    {
        return Exercise::whereIn('id', Arr::pluck($this->items, 'exercise_id'))->with('muscleGroups')->get()->keyBy('id');
    }

    public function render()
    {
        return view('livewire.workouts.form')->title($this->workout ? 'Workout bewerken' : 'Nieuwe workout');
    }

    /** @return list<int> */
    private function primaryGroupIds(): array
    {
        return $this->itemExercises->map(fn (Exercise $exercise) => $exercise->primaryMuscleGroup()?->id)->filter()->unique()->values()->all();
    }
}
