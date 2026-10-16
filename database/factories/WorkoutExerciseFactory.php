<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkoutExercise> */
class WorkoutExerciseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workout_id' => Workout::factory(),
            'exercise_id' => Exercise::factory(),
            'position' => 0,
            'target_sets' => 3,
            'target_reps' => 10,
            'rest_seconds' => 90,
        ];
    }
}
