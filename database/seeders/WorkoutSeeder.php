<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Database\Seeder;

/**
 * Drie voorbeeldworkouts met de gym-machines, voor de eerste gebruiker (Antoine).
 * Kan vaker gedraaid worden: de oefeningen van deze workouts worden dan opnieuw gezet.
 */
class WorkoutSeeder extends Seeder
{
    /** slug => [sets, reps, kg] of voor cardio [minuten, waarde van de instelling van het apparaat] */
    private const WORKOUTS = [
        'Push' => ['#f97316', 'Borst, schouders en triceps.', [
            'chest-press-machine' => [3, 10, 40],
            'shoulder-press' => [3, 10, 25],
            'pec-fly-machine' => [3, 12, 30],
            'deltoid-raise' => [3, 12, 15],
            'seated-triceps-press' => [3, 12, 25],
        ]],
        'Pull' => ['#1d63c5', 'Rug en biceps.', [
            'lat-pull-down-cable-station' => [3, 10, 45],
            'seated-row' => [3, 10, 40],
            'rear-delt-machine' => [3, 12, 20],
            'bilateral-arm-curl-machine' => [3, 12, 20],
            'back-extension-machine' => [3, 15, 30],
        ]],
        'Benen + cardio' => ['#0e9f8a', 'Benen op de machines en afsluiten op de crosstrainer.', [
            'leg-press-machine' => [4, 10, 80],
            'leg-extension-machine' => [3, 12, 35],
            'seated-leg-curl' => [3, 12, 30],
            'abduction' => [3, 15, 35],
            'adduction' => [3, 15, 35],
            'cross-trainer' => [15, 8],
        ]],
    ];

    public function run(): void
    {
        $user = User::where('email', 'antoine@myfitness.test')->first() ?? User::first();

        if (! $user) {
            return;
        }

        foreach (self::WORKOUTS as $name => [$color, $description, $targets]) {
            $workout = Workout::updateOrCreate(
                ['user_id' => $user->id, 'slug' => str($name)->slug()->value()],
                ['name' => $name, 'color' => $color, 'description' => $description],
            );
            $workout->exercises()->delete();

            $position = 0;
            foreach ($targets as $slug => $target) {
                $exercise = Exercise::whereNull('user_id')->where('slug', $slug)->first();

                if (! $exercise) {
                    continue;
                }

                $values = $exercise->type->usesSets()
                    ? ['target_sets' => $target[0], 'target_reps' => $target[1], 'target_weight' => $target[2]]
                    : ['target_duration_seconds' => $target[0] * 60, 'target_metric_value' => $target[1]];

                $workout->exercises()->create([
                    ...WorkoutExercise::defaultsFor($exercise),
                    ...$values,
                    'exercise_id' => $exercise->id,
                    'position' => $position++,
                ]);
            }

            $workout->load('exercises.exercise.muscleGroups');
            $workout->muscleGroups()->sync($workout->exercises->map(fn ($item) => $item->exercise->primaryMuscleGroup()?->id)->filter()->unique());
        }
    }
}
