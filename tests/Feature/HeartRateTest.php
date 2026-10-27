<?php

use App\Actions\StartWorkoutSession;
use App\Livewire\Profile\UpdatePersonalDetails;
use App\Livewire\Progress\Index;
use App\Livewire\Sessions\Run;
use App\Livewire\Sessions\Summary;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Support\HeartRateZones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('heart rate zones follow the estimated max heart rate', function () {
    $zones = new HeartRateZones(170);

    expect($zones->all()[0])->toBe(['number' => 1, 'name' => 'Herstel', 'from' => 85, 'to' => 102])
        ->and($zones->zoneFor(140))->toMatchArray(['number' => 4, 'name' => 'Anaeroob', 'percent' => 82])
        ->and($zones->zoneFor(80))->toBeNull()
        ->and($zones->zoneFor(null))->toBeNull()
        ->and(HeartRateZones::forUser(User::factory()->create()))->toBeNull();
});

test('the profile shows your zones once your birth date is known', function () {
    $user = User::factory()->create(['birth_date' => now()->subYears(54)->subMonth()]);

    Livewire::actingAs($user)->test(UpdatePersonalDetails::class)
        ->assertSee('Hartslagzones')
        ->assertSee('Zone 5');
});

test('cardio shows the zone of your average heart rate', function () {
    $user = User::factory()->create(['birth_date' => now()->subYears(54)->subMonth()]); // max 170
    $bike = Exercise::factory()->cardio()->create(['name' => 'Hometrainer']);
    $workout = Workout::factory()->for($user)->create();
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $bike->id, 'position' => 0, 'target_sets' => null, 'target_reps' => null, 'rest_seconds' => null, 'target_duration_seconds' => 1200]);

    $session = app(StartWorkoutSession::class)->handle($user, $workout);
    $session->exercises()->first()->cardio->update(['duration_seconds' => 1200, 'avg_heart_rate' => 130]);

    Livewire::actingAs($user)->test(Run::class, ['session' => $session])
        ->assertSee('Zone 3 · Aeroob · 76%');

    $session->finish();

    Livewire::actingAs($user)->test(Summary::class, ['session' => $session])
        ->assertSee('Zone 3 · Aeroob · 76%');
});

test('progress shows your weight over the last year', function () {
    $user = User::factory()->create(['target_weight_kg' => 85]);
    $user->bodyWeights()->create(['measured_on' => today()->subMonths(13), 'weight_kg' => 99]);
    $user->bodyWeights()->create(['measured_on' => today()->subMonths(2), 'weight_kg' => 90.5]);
    $user->bodyWeights()->create(['measured_on' => today(), 'weight_kg' => 88.1]);

    Livewire::actingAs($user)->test(Index::class)
        ->assertSee('88,1')
        ->assertSee('-2,4 kg')
        ->assertSee('Streefgewicht')
        ->assertDontSee('99 kg');
});

test('progress invites you to start weighing', function () {
    Livewire::actingAs(User::factory()->create())->test(Index::class)
        ->assertSee('Nog geen wegingen');
});
