<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Workout> */
class WorkoutFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => str($name)->slug(),
            'color' => fake()->randomElement(Workout::COLORS),
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
