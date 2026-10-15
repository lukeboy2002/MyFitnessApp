<?php

namespace Database\Factories;

use App\Models\BodyPart;
use App\Models\MuscleGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MuscleGroup> */
class MuscleGroupFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'body_part_id' => BodyPart::factory(),
            'user_id' => null,
            'name' => ucfirst($name),
            'slug' => str($name)->slug(),
            'color' => fake()->hexColor(),
            'sort_order' => 0,
        ];
    }
}
