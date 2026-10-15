<?php

namespace Database\Factories;

use App\Models\BodyPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BodyPart> */
class BodyPartFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['user_id' => null, 'name' => ucfirst($name), 'slug' => str($name)->slug(), 'sort_order' => 0];
    }
}
