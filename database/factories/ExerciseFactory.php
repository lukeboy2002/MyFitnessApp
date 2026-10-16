<?php

namespace Database\Factories;

use App\Enums\Equipment;
use App\Enums\ExerciseType;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Exercise> */
class ExerciseFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'user_id' => null,
            'name' => ucfirst($name),
            'slug' => str($name)->slug(),
            'type' => ExerciseType::Strength,
            'equipment' => Equipment::Machine,
            'default_sets' => 3,
            'default_reps' => 10,
        ];
    }

    public function cardio(): static
    {
        return $this->state(['type' => ExerciseType::Cardio, 'equipment' => Equipment::CardioMachine, 'default_sets' => null, 'default_reps' => null, 'default_duration_seconds' => 1200]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
