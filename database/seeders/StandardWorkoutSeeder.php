<?php

namespace Database\Seeders;

use App\Enums\ExerciseType;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Kant-en-klare workouts (user_id null) voor Workouts › Kant-en-klaar. Alleen standaardoefeningen.
 * Mag online draaien (geen Faker) en vaker: een workout die er al is (zelfde slug) wordt niet aangeraakt,
 * zodat wijzigingen van de admin blijven staan. Weggegooid? Dan komt hij bij een volgende keer terug.
 *
 * php artisan db:seed --class=StandardWorkoutSeeder --force
 */
class StandardWorkoutSeeder extends Seeder
{
    /**
     * naam => [kleur, omschrijving, [slug => [sets, herhalingen]]]
     * Type tijd (plank): [sets, seconden]. Cardio: [minuten].
     */
    public const WORKOUTS = [
        'Armen met barbell' => ['#e5650f', 'Biceps en triceps om en om, de zwaarste oefeningen eerst.', [
            'bicep-curl-barbell' => [3, 10],
            'bench-press-close-grip-barbell' => [3, 8],
            'preacher-curl-barbell' => [3, 10],
            'skullcrusher-barbell' => [3, 10],
            'reverse-curl-barbell' => [2, 12],
        ]],
        'Armen met dumbbells' => ['#f97316', 'Biceps en triceps met dumbbells, ook goed thuis te doen.', [
            'bicep-curl-dumbbell' => [3, 10],
            'triceps-extension-dumbbell' => [3, 10],
            'hammer-curl-dumbbell' => [3, 12],
            'triceps-kickback-dumbbell' => [3, 12],
            'concentration-curl-dumbbell' => [2, 12],
        ]],
        'Borst en triceps' => ['#db3f6b', 'Drukken met barbell en dumbbells, afsluiten met triceps en push-ups.', [
            'bench-press-barbell' => [4, 8],
            'incline-bench-press-dumbbell' => [3, 10],
            'chest-fly-dumbbell' => [3, 12],
            'triceps-pushdown-cable' => [3, 12],
            'push-up' => [2, 15],
        ]],
        'Rug en biceps' => ['#1d63c5', 'Trekken: verticaal, horizontaal en de achterkant van de schouders.', [
            'lat-pull-down-cable-station' => [3, 10],
            'bent-over-row-barbell' => [3, 8],
            'seated-cable-row-bar-grip' => [3, 10],
            'face-pull-cable' => [3, 15],
            'bicep-curl-dumbbell' => [3, 10],
        ]],
        'Schouders' => ['#8b5cf6', 'Drukken boven je hoofd en de schouders van alle kanten.', [
            'overhead-press-barbell' => [4, 8],
            'lateral-raise-dumbbell' => [3, 12],
            'front-raise-dumbbell' => [3, 12],
            'rear-delt-reverse-fly-dumbbell' => [3, 12],
            'shrug-dumbbell' => [3, 12],
        ]],
        'Benen machines' => ['#65a30d', 'Benen en billen op de machines, veilig voor beginners en gevorderden.', [
            'leg-press-machine' => [4, 10],
            'leg-extension-machine' => [3, 12],
            'seated-leg-curl' => [3, 12],
            'hip-thrust-machine' => [3, 10],
            'abduction' => [2, 15],
            'standing-calf-raise-machine' => [3, 15],
        ]],
        'Benen met barbell' => ['#0e9f8a', 'Squat, romanian deadlift en lunges: de klassiekers met de stang.', [
            'squat-barbell' => [4, 8],
            'romanian-deadlift-barbell' => [3, 10],
            'lunge-barbell' => [3, 10],
            'hip-thrust-barbell' => [3, 10],
            'standing-calf-raise-barbell' => [3, 15],
        ]],
        'Full body machines' => ['#00468c', 'Het hele lichaam in één keer op de machines, met cardio als afsluiter.', [
            'chest-press-machine' => [3, 10],
            'lat-pulldown-machine' => [3, 10],
            'leg-press-machine' => [3, 10],
            'shoulder-press' => [3, 10],
            'seated-row' => [3, 10],
            'crunch-machine' => [2, 15],
            'cross-trainer' => [10],
        ]],
        'Thuis zonder materiaal' => ['#d4a106', 'Alleen je eigen lichaam: overal te doen, zonder apparaten.', [
            'squat-bodyweight' => [3, 15],
            'push-up' => [3, 12],
            'lunge' => [3, 10],
            'glute-bridge' => [3, 15],
            'plank' => [3, 30],
            'mountain-climber' => [5],
        ]],
        'Buik en core' => ['#64748b', 'Kort en stevig: buikspieren, schuine buikspieren en onderrug.', [
            'crunch' => [3, 15],
            'lying-leg-raise' => [3, 12],
            'russian-twist-bodyweight' => [3, 20],
            'dead-bug' => [3, 12],
            'plank' => [3, 45],
            'side-plank' => [2, 30],
        ]],
    ];

    public function run(): void
    {
        foreach (self::WORKOUTS as $name => [$color, $description, $targets]) {
            $slug = str($name)->slug()->value();

            if (Workout::whereNull('user_id')->where('slug', $slug)->exists()) {
                continue;
            }

            $exercises = Exercise::whereNull('user_id')->whereIn('slug', array_keys($targets))->with('muscleGroups')->get()->keyBy('slug');

            DB::transaction(function () use ($name, $slug, $color, $description, $targets, $exercises) {
                $workout = Workout::create(['user_id' => null, 'name' => $name, 'slug' => $slug, 'color' => $color, 'description' => $description]);

                $position = 0;
                foreach ($targets as $exerciseSlug => $target) {
                    if (! $exercise = $exercises->get($exerciseSlug)) {
                        continue;
                    }

                    $values = match ($exercise->type) {
                        ExerciseType::Cardio => ['target_duration_seconds' => $target[0] * 60],
                        ExerciseType::Duration => ['target_sets' => $target[0], 'target_duration_seconds' => $target[1]],
                        default => ['target_sets' => $target[0], 'target_reps' => $target[1], 'target_weight' => null],
                    };

                    $workout->exercises()->create([
                        ...WorkoutExercise::defaultsFor($exercise),
                        ...$values,
                        'exercise_id' => $exercise->id,
                        'position' => $position++,
                    ]);
                }

                $workout->muscleGroups()->sync($exercises->map(fn (Exercise $exercise) => $exercise->primaryMuscleGroup()?->id)->filter()->unique()->values());
            });
        }
    }
}
