<?php

namespace App\Livewire\Sessions\Concerns;

use App\Actions\AddSessionExercise;
use App\Enums\ExerciseType;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\WorkoutExercise;
use App\Support\Supersets;
use Illuminate\Support\Facades\Auth;

/**
 * Trainingsscherm: oefeningen toevoegen, verslepen, achteraan zetten, als superset koppelen en weghalen.
 * Hoort bij Sessions\Run (met PicksExercises en EditsExerciseTargets).
 */
trait ArrangesExercises
{
    use ConfirmsDeletion;

    /** Oefening die je tijdens de training toevoegt: eerst kiezen, dan het doel invullen */
    public ?array $adding = null;

    /** Oefening toevoegen: kiezer openen */
    public function openAdding(): void
    {
        $this->adding = null;
        $this->resetErrorBag('adding');
        $this->openPicker();
    }

    /** Oefening gekozen: het doel invullen, voorgevuld met het standaarddoel van de oefening */
    public function pick(int $id): void
    {
        $exercise = Exercise::visibleTo(Auth::user())->active()->findOrFail($id);
        $this->adding = $this->toItem($exercise, WorkoutExercise::defaultsFor($exercise, Auth::user()));
    }

    public function addExercise(AddSessionExercise $action): void
    {
        $this->ensureRunning();
        abort_unless($this->adding, 404);

        $this->adding = $this->normalizeDecimals($this->adding);
        $this->validate([
            'adding.exercise_id' => ['required', 'integer'],
            ...$this->targetRules('adding'),
        ], attributes: $this->targetAttributes('adding'));

        $exercise = Exercise::visibleTo(Auth::user())->active()->findOrFail($this->adding['exercise_id']);
        $item = $action->handle($this->session, $exercise, $this->targets($exercise->type, $this->adding));

        $this->reset('adding', 'showPicker');
        $this->fillInputs();
        $this->dispatch('exercise-added', id: $item->id);
    }

    /** wire:sort: oefening $id naar plek $position slepen, bijv. als een machine bezet is. Een superset-paar gaat samen mee. */
    public function sortExercise(int $id, int $position): void
    {
        $this->findExercise($id);

        [$order, $linked] = $this->supersetState();
        $this->savePositions(Supersets::move($order, $linked, $id, $position));
    }

    /** Superset: oefening $id koppelen aan de volgende, of het paar weer losmaken (ook bij Quickstart) */
    public function toggleSuperset(int $id): void
    {
        $this->findExercise($id);

        [$order, $linked, $cardio] = $this->supersetState();
        $first = Supersets::block($order, $linked, $id)[0];

        if ($linked[$first]) {
            $this->session->exercises()->whereKey($first)->update(['superset_next' => false]);
        } elseif (Supersets::canLink($order, $linked, $cardio, $id)) {
            $this->session->exercises()->whereKey($id)->update(['superset_next' => true]);
        }
    }

    /** Per oefening: kan hij aan de volgende gekoppeld worden? (voor het knopje "Superset met volgende") */
    public function linkable(): array
    {
        [$order, $linked, $cardio] = $this->supersetState();

        return collect($order)->mapWithKeys(fn ($id) => [$id => Supersets::canLink($order, $linked, $cardio, $id)])->all();
    }

    /** Oefening uit deze training halen (met sets en cardio); de workout zelf blijft zoals hij is */
    public function confirmDelete(int $id): void
    {
        $item = $this->findExercise($id);

        $this->deletingId = $item->id;
        $this->deletingName = $item->exercise->name;
        $this->confirmingDeletion = true;
    }

    public function delete(int $id): void
    {
        $this->findExercise($id)->delete();

        $this->savePositions($this->session->exercises()->pluck('id')->all());
        unset($this->cardio[$id], $this->splits[$id]);
    }

    protected function deletableModel(): string
    {
        return SessionExercise::class;
    }

    /** Machine bezet: oefening (of het hele superset-paar) achteraan zetten */
    public function moveToEnd(int $id): void
    {
        $this->sortExercise($id, PHP_INT_MAX);
    }

    /** @return array{0: list<int>, 1: array<int, bool>, 2: array<int, bool>} volgorde, gekoppeld, cardio */
    private function supersetState(): array
    {
        $items = $this->session->exercises()->with('exercise')->get();

        return [
            $items->pluck('id')->all(),
            $items->mapWithKeys(fn (SessionExercise $item) => [$item->id => $item->superset_next])->all(),
            $items->mapWithKeys(fn (SessionExercise $item) => [$item->id => $item->exercise->type === ExerciseType::Cardio])->all(),
        ];
    }

    /** Nieuwe volgorde opslaan en koppelingen die niet meer kloppen weghalen */
    private function savePositions(array $ids): void
    {
        foreach ($ids as $index => $exerciseId) {
            $this->session->exercises()->whereKey($exerciseId)->update(['position' => $index + 1]);
        }

        $valid = Supersets::normalize(...$this->supersetState());
        foreach ($this->session->exercises()->where('superset_next', true)->pluck('id') as $exerciseId) {
            $valid[$exerciseId] || $this->session->exercises()->whereKey($exerciseId)->update(['superset_next' => false]);
        }
    }
}
