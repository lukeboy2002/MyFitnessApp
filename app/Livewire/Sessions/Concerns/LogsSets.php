<?php

namespace App\Livewire\Sessions\Concerns;

use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Support\ExerciseRecords;
use App\Support\ProgressionHint;
use App\Support\RestReminder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Trainingsscherm: sets invullen, afvinken (met rusttimer en records), toevoegen en weghalen.
 * Hoort bij Sessions\Run; gebruikt daar $session, findSet(), findExercise() en decimal().
 */
trait LogsSets
{
    /** Invoer per set: [set_id => ['reps' => .., 'weight' => .., 'seconds' => ..]] */
    public array $values = [];

    public function updatedValues($value, string $key): void
    {
        [$id, $field] = explode('.', $key);
        $set = $this->findSet((int) $id);
        $value = $this->decimal($value);

        $rules = ['reps' => 'integer|between:0,500', 'weight' => 'numeric|between:-200,999', 'seconds' => 'integer|between:0,36000'];
        if (! isset($rules[$field]) || Validator::make(['v' => $value], ['v' => 'nullable|'.$rules[$field]])->fails()) {
            $this->addError("values.{$id}.{$field}", __('Invalid value'));

            return;
        }

        $this->resetErrorBag("values.{$id}.{$field}");
        $set->update([$field === 'seconds' ? 'duration_seconds' : $field => $value]);
    }

    /** Het vinkje van een set; bij afvinken start de rusttimer */
    public function toggleSet(int $id): void
    {
        $set = $this->findSet($id);
        $done = ! $set->is_completed;

        $set->update(['is_completed' => $done, 'completed_at' => $done ? now() : null]);

        // Nieuw record: confetti en een melding
        $exercise = $set->sessionExercise->exercise;

        $rest = $set->sessionExercise->rest_seconds;
        if ($done && $rest) {
            $this->dispatch('rest-start', seconds: $rest);

            // Ook met je scherm uit: pushmelding als de rust voorbij is
            $next = $set->sessionExercise->sets()->where('is_completed', false)->orderBy('set_number')->first();
            RestReminder::schedule(Auth::user(), $rest,
                $next ? __('Time for set :number of :exercise.', ['number' => $next->set_number, 'exercise' => $exercise->name]) : __('Time for your next exercise.'),
                route('sessions.run', $this->session));
        }

        $records = $done ? ExerciseRecords::build($this->session, $exercise->id, $exercise->type) : null;
        if ($records?->isRecordSet($set)) {
            $this->dispatch('record', title: __('New record!'), text: $exercise->name.' · '.$records->describeSet($set));
        }
    }

    /** Het voorstel uit de vorige keer (ProgressionHint) invullen in de sets die nog niet zijn afgevinkt */
    public function applyHint(int $sessionExerciseId): void
    {
        $item = $this->findExercise($sessionExerciseId);
        $records = ExerciseRecords::forSession($this->session)[$item->exercise_id] ?? null;
        $hint = ProgressionHint::for($item->exercise->type, $records?->lastSets ?? [], $item->target_reps);

        if (! $hint) {
            return;
        }

        foreach ($item->sets()->where('is_completed', false)->get() as $set) {
            $set->update(['reps' => $hint->reps, ...($hint->weight !== null ? ['weight' => $hint->weight] : [])]);
            $this->values[$set->id] = $this->setValues($set);
        }
    }

    public function addSet(int $sessionExerciseId): void
    {
        $item = $this->findExercise($sessionExerciseId);
        $last = $item->sets()->reorder('set_number', 'desc')->first();

        $set = $item->sets()->create([
            ...($last?->only('target_reps', 'target_weight', 'target_duration_seconds') ?? []),
            'set_number' => ($last?->set_number ?? 0) + 1,
            'reps' => $last?->reps ?? $item->target_reps,
            'weight' => $last?->weight ?? $item->target_weight,
            'duration_seconds' => $last?->duration_seconds ?? ($item->exercise->type->usesReps() ? null : $item->target_duration_seconds),
        ]);
        $this->values[$set->id] = $this->setValues($set);
    }

    public function removeSet(int $sessionExerciseId): void
    {
        $item = $this->findExercise($sessionExerciseId);

        if ($item->sets()->count() > 1) {
            $set = $item->sets()->reorder('set_number', 'desc')->first();
            unset($this->values[$set->id]);
            $set->delete();
        }
    }

    private function setValues(SetLog $set): array
    {
        return [
            'reps' => $set->reps,
            'weight' => filled($set->weight) ? WorkoutSession::formatNumber($set->weight) : null,
            'seconds' => $set->duration_seconds,
        ];
    }
}
