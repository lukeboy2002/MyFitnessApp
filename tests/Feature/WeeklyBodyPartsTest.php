<?php

use App\Actions\StartWorkoutSession;
use App\Livewire\Dashboard;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Support\TrainingStats;
use Carbon\CarbonImmutable;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-19 18:00'));   // donderdag
    $this->user = User::factory()->create(['locale' => 'nl', 'onboarded_at' => now()]);
});

/** Afgeronde training: [spiergroep-slug => [opwarmset?, ...]] */
function trainBodyParts(array $plan, string $when): void
{
    test()->travelTo(CarbonImmutable::parse($when));
    $workout = Workout::factory()->for(test()->user)->create();

    foreach (array_keys($plan) as $position => $slug) {
        $exercise = Exercise::factory()->create();
        $exercise->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', $slug)->value('id'));
        WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $exercise->id, 'position' => $position]);
    }

    $session = app(StartWorkoutSession::class)->handle(test()->user, $workout);
    foreach ($session->exercises()->with('exercise.muscleGroups')->get() as $item) {
        $item->sets()->delete();
        foreach ($plan[$item->exercise->primaryMuscleGroup()->slug] as $i => $warmup) {
            $item->sets()->create(['set_number' => $i + 1, 'weight' => 20, 'reps' => 10, 'is_warmup' => $warmup, 'is_completed' => true]);
        }
    }
    $session->finish();
    test()->travelTo(CarbonImmutable::parse('2026-11-19 18:00'));
}

test('sets per body part this week, without warm-ups, with zeros for what you skip', function () {
    trainBodyParts(['chest' => [true, false, false, false], 'triceps' => [false, false]], '2026-11-17 19:00');
    trainBodyParts(['biceps' => [false, false, false]], '2026-11-18 19:00');
    trainBodyParts(['lats' => [false, false]], '2026-11-12 19:00');   // vorige week

    $parts = (new TrainingStats($this->user))->bodyParts(CarbonImmutable::now()->startOfWeek())->keyBy('slug');

    expect($parts['chest']['sets'])->toBe(3)                     // opwarmset telt niet
        ->and($parts['arms']['sets'])->toBe(5)
        ->and($parts['arms']['groups']->pluck('name')->all())->toBe(['Biceps', 'Triceps'])
        ->and($parts['back']['sets'])->toBe(0)                    // wel in de lijst: je slaat rug over
        ->and($parts->has('neck'))->toBeFalse()                   // nek en full body alleen als je ze trainde
        ->and($parts->has('full-body'))->toBeFalse();

    Livewire::actingAs($this->user)->test(Dashboard::class)
        ->assertSee(['Spiergroepen deze week', '3 sets', '5 sets', 'Biceps 3 · Triceps 2', 'nog niet', 'vorige week 2']);
});
