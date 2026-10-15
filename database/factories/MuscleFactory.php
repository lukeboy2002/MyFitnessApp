<?php

namespace Database\Factories;

use App\Models\Muscle;
use App\Models\MuscleGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Muscle> */
class MuscleFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'muscle_group_id' => MuscleGroup::factory(),
            'user_id' => null,
            'name' => ucfirst($name),
            'latin_name' => null,
            'slug' => str($name)->slug(),
            'sort_order' => 0,
        ];
    }
}
