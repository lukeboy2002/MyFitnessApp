<?php

namespace App\Actions;

use App\Enums\SessionStatus;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\DB;

/**
 * Start een training vanuit een workout: de oefeningen en doelen worden gekopieerd,
 * voor sets worden de set-regels alvast aangemaakt en voor cardio het logformulier.
 * Er loopt maar één training tegelijk; loopt er al een, dan krijg je die terug.
 */
class StartWorkoutSession
{
    public function handle(User $user, Workout $workout): WorkoutSession
    {
        if ($running = WorkoutSession::where('user_id', $user->id)->running()->first()) {
            return $running;
        }

        return DB::transaction(function () use ($user, $workout) {
            $session = WorkoutSession::create([
                'user_id' => $user->id,
                'workout_id' => $workout->id,
                'name' => $workout->name,
                'color' => $workout->color,
                'status' => SessionStatus::Active,
                'started_at' => now(),
            ]);

            $workout->exercises()->with('exercise')->get()->each(function (WorkoutExercise $item, int $position) use ($session) {
                $sessionExercise = $session->exercises()->create([
                    ...$item->only('exercise_id', 'target_sets', 'target_reps', 'target_weight', 'rest_seconds',
                        'target_duration_seconds', 'target_metric', 'target_metric_value', 'notes'),
                    'workout_exercise_id' => $item->id,
                    'position' => $position,
                ]);

                $type = $item->exercise->type;

                if ($type->usesSets()) {
                    foreach (range(1, max(1, $item->target_sets ?? 1)) as $number) {
                        $sessionExercise->sets()->create([
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
                    $sessionExercise->cardio()->create([
                        'duration_seconds' => $item->target_duration_seconds,
                        'metric' => $item->target_metric,
                        'metric_value' => $item->target_metric_value,
                    ]);
                }
            });

            return $session;
        });
    }
}
