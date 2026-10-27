<?php

use App\Actions\StartWorkoutSession;
use App\Enums\FitnessGoal;
use App\Livewire\Dashboard;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Support\GoalProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Een afgeronde training $daysAgo dagen geleden met één krachtoefening: [gewicht, reps] per set */
function trainingWithSets(User $user, Exercise $press, int $daysAgo, array $sets): void
{
    $workout = Workout::factory()->for($user)->create();
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $press->id, 'position' => 0, 'target_sets' => 1, 'target_reps' => 10, 'target_weight' => 40]);

    test()->travelTo(now()->subDays($daysAgo));
    $session = app(StartWorkoutSession::class)->handle($user, $workout);
    $item = $session->exercises()->first();
    $item->sets()->delete();
    foreach ($sets as $i => [$weight, $reps]) {
        $item->sets()->create(['set_number' => $i + 1, 'weight' => $weight, 'reps' => $reps, 'is_completed' => true]);
    }
    $session->finish();
    test()->travelBack();
}

test('without a goal the dashboard asks you to choose one', function () {
    expect(GoalProgress::for($user = User::factory()->create()))->toBeNull();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('Nog geen doel gekozen')
        ->assertSee('Doel kiezen');
});

test('losing weight shows the way to your target weight', function () {
    $user = User::factory()->create(['goal' => FitnessGoal::LoseWeight, 'target_weight_kg' => 80, 'birth_date' => now()->subYears(54)->subMonth()]);
    $user->bodyWeights()->create(['measured_on' => today()->subDays(60), 'weight_kg' => 90]);
    $user->bodyWeights()->create(['measured_on' => today()->subDays(40), 'weight_kg' => 88]);
    $user->bodyWeights()->create(['measured_on' => today(), 'weight_kg' => 86.5]);

    $progress = GoalProgress::for($user);

    expect($progress->value)->toBe('86,5')
        ->and($progress->detail)->toContain('-1,5 kg sinds')
        ->and($progress->detail)->toContain('nog 6,5 kg tot 80 kg')
        ->and($progress->percent)->toBe(35)
        ->and($progress->tip)->toContain('zone 2 (102 tot 119 bpm)');

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('Afvallen')
        ->assertSee('Weging invullen');
});

test('losing weight without weighings asks you to weigh yourself', function () {
    $user = User::factory()->create(['goal' => FitnessGoal::LoseWeight]);

    expect(GoalProgress::for($user)->value)->toBeNull()
        ->and(GoalProgress::for($user)->detail)->toBe('Weeg jezelf om je voortgang te zien.');
});

test('strength counts the records of the last 30 days', function () {
    $user = User::factory()->create(['goal' => FitnessGoal::Strength]);
    $press = Exercise::factory()->create(['name' => 'Chest Press']);

    trainingWithSets($user, $press, 50, [[40, 10]]);
    trainingWithSets($user, $press, 10, [[45, 10]]);

    $progress = GoalProgress::for($user);

    expect($progress->value)->toBe('1')
        ->and($progress->unit)->toBe('records')
        ->and($progress->detail)->toBe('De 30 dagen ervoor: nog niets');
});

test('stay fit compares the number of trainings', function () {
    $user = User::factory()->create(['goal' => FitnessGoal::StayFit]);
    $press = Exercise::factory()->create();

    trainingWithSets($user, $press, 45, [[40, 10]]);
    trainingWithSets($user, $press, 20, [[40, 10]]);
    trainingWithSets($user, $press, 5, [[40, 10]]);

    $progress = GoalProgress::for($user);

    expect($progress->value)->toBe('2')
        ->and($progress->unit)->toBe('trainingen')
        ->and($progress->detail)->toBe('+100% t.o.v. de 30 dagen ervoor (1)');
});
