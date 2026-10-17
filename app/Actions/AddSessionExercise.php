<?php

namespace App\Actions;

use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\WorkoutSession;

/**
 * Een oefening aan een training toevoegen, met doelen zoals in een workout (target_*, rest_seconds, notes).
 * Voor sets worden de set-regels alvast aangemaakt en voor cardio het logformulier.
 */
class AddSessionExercise
{
    public function handle(WorkoutSession $session, Exercise $exercise, array $targets, ?int $workoutExerciseId = null): SessionExercise
    {
        $item = $session->exercises()->create([
            ...collect($targets)->only('target_sets', 'target_reps', 'target_weight', 'rest_seconds',
                'target_duration_seconds', 'target_metric', 'target_metric_value', 'notes')->all(),
            'exercise_id' => $exercise->id,
            'workout_exercise_id' => $workoutExerciseId,
            'position' => ($session->exercises()->max('position') ?? -1) + 1,
        ]);

        $type = $exercise->type;

        if ($type->usesSets()) {
            foreach (range(1, max(1, $item->target_sets ?? 1)) as $number) {
                $item->sets()->create([
                    'set_number' => $number,
                    'target_reps' => $type->usesReps() ? $item->target_reps : null,
                    'target_weight' => $item->target_weight,
                    'target_duration_seconds' => $type->usesReps() ? null : $item->target_duration_seconds,
                    'reps' => $type->usesReps() ? $item->target_reps : null,
                    'weight' => $item->target_weight,
                    'duration_seconds' => $type->usesReps() ? null : $item->target_duration_seconds,
                ]);
            }
        } else {
            $item->cardio()->create([
                'duration_seconds' => $item->target_duration_seconds,
                'metric' => $item->target_metric,
                'metric_value' => $item->target_metric_value,
            ]);
        }

        return $item;
    }
}
