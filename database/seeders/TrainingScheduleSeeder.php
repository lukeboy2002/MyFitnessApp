<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Database\Seeder;

/**
 * Trainingsschema van 19 oktober 2026: vier workouts (duwen, trekken, benen, licht) op basis van de oude app.
 * Online: php artisan db:seed --class=TrainingScheduleSeeder --force
 * Kan vaker gedraaid worden: de oefeningen van deze vier workouts worden dan opnieuw gezet, je trainingen blijven staan.
 */
class TrainingScheduleSeeder extends Seeder
{
    private const EMAIL = 'antoine@la-online.nl';

    private const PROGRESS = 'Doorgroeien naar 15 herhalingen; haal je 4 × 15, dan de eerste set een stap zwaarder.';

    /** slug => [sets, reps, kg, notitie?] of voor cardio [minuten, waarde van de instelling van het apparaat] */
    private const WORKOUTS = [
        'A. Borst, schouders en triceps' => ['#f97316', 80, 'Duwen. Tijden van loopband en hometrainer inclusief 2 minuten cooldown.', [
            'treadmill' => [20, 5.2],
            'chest-press-machine' => [4, 12, 27, 'Opbouwen: 23, 23, 27, 27 kg.'],
            'pec-fly-machine' => [4, 12, 50, self::PROGRESS],
            'shoulder-press' => [4, 12, 32],
            'deltoid-raise' => [4, 12, 27, self::PROGRESS],
            'seated-triceps-press' => [4, 12, 46, self::PROGRESS],
            'cross-trainer' => [15, 6],
        ]],
        'B. Rug, achterkant schouders en biceps' => ['#1d63c5', 85, 'Trekken. Tijden van loopband en hometrainer inclusief 2 minuten cooldown.', [
            'hometrainer' => [20, 10],
            'lat-pull-down-cable-station' => [4, 12, 45, self::PROGRESS],
            'seated-row' => [4, 12, 46],
            'pullover-machine' => [4, 12, 46, self::PROGRESS],
            'rear-delt-machine' => [4, 12, 46],
            'bilateral-arm-curl-machine' => [4, 12, 27, self::PROGRESS],
            'treadmill' => [15, 5.2],
        ]],
        'C. Benen en core' => ['#0e9f8a', 90, 'Benen. Kuiten (Tilt Seat Calf, 4 × 12, 20 kg) erbij als je onder de 90 minuten blijft.', [
            'cross-trainer' => [15, 6],
            'leg-press-machine' => [4, 12, 73, self::PROGRESS],
            'leg-extension-machine' => [4, 12, 46, self::PROGRESS],
            'seated-leg-curl' => [4, 12, 50, self::PROGRESS],
            'abduction' => [4, 12, 50, self::PROGRESS],
            'adduction' => [4, 12, 50, self::PROGRESS],
            'ab-crush-machine' => [4, 12, 50],
            'hometrainer' => [15, 9],
        ]],
        'D. Cardio en core' => ['#8b5cf6', 65, 'Licht, voor een vierde dag of als je weinig energie hebt.', [
            'treadmill' => [25, 5.2],
            'back-extension-machine' => [4, 12, 46, self::PROGRESS],
            'ab-crush-machine' => [4, 12, 50],
            'hometrainer' => [20, 10],
            'cross-trainer' => [10, 6],
        ]],
    ];

    public function run(): void
    {
        $user = User::where('email', self::EMAIL)->first();

        if (! $user) {
            $this->command?->warn('Geen gebruiker met e-mailadres '.self::EMAIL.', er zijn geen workouts aangemaakt.');

            return;
        }

        foreach (self::WORKOUTS as $name => [$color, $minutes, $description, $targets]) {
            $workout = Workout::updateOrCreate(
                ['user_id' => $user->id, 'slug' => str($name)->slug()->value()],
                ['name' => $name, 'color' => $color, 'description' => $description, 'estimated_minutes' => $minutes],
            );
            $workout->exercises()->delete();

            $position = 0;
            foreach ($targets as $slug => $target) {
                $exercise = Exercise::whereNull('user_id')->where('slug', $slug)->first();

                if (! $exercise) {
                    $this->command?->warn("Oefening {$slug} niet gevonden, overgeslagen in {$name}.");

                    continue;
                }

                $values = $exercise->type->usesSets()
                    ? ['target_sets' => $target[0], 'target_reps' => $target[1], 'target_weight' => $target[2], 'notes' => $target[3] ?? null]
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

        $this->command?->info('Vier workouts aangemaakt voor '.self::EMAIL.'.');
    }
}
