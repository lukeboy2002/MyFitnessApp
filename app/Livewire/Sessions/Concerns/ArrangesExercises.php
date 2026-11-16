<?php

namespace App\Livewire\Sessions\Concerns;

use App\Actions\AddSessionExercise;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\WorkoutExercise;
use Illuminate\Support\Facades\Auth;

/**
 * Trainingsscherm: oefeningen toevoegen, verslepen, achteraan zetten en weghalen.
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

    /** wire:sort: oefening $id naar plek $position slepen, bijv. als een machine bezet is */
    public function sortExercise(int $id, int $position): void
    {
        $this->findExercise($id);

        $ids = $this->session->exercises()->pluck('id')->reject(fn ($other) => $other === $id)->values()->all();
        array_splice($ids, max(0, min($position, count($ids))), 0, [$id]);

        foreach ($ids as $index => $exerciseId) {
            $this->session->exercises()->whereKey($exerciseId)->update(['position' => $index + 1]);
        }
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

        $this->session->exercises()->pluck('id')->each(
            fn ($exerciseId, $index) => $this->session->exercises()->whereKey($exerciseId)->update(['position' => $index + 1])
        );
        unset($this->cardio[$id], $this->splits[$id]);
    }

    protected function deletableModel(): string
    {
        return SessionExercise::class;
    }

    /** Machine bezet: oefening achteraan zetten */
    public function moveToEnd(int $id): void
    {
        $this->sortExercise($id, PHP_INT_MAX);
    }
}
