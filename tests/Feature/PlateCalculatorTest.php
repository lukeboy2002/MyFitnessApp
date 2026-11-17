<?php

use App\Actions\StartWorkoutSession;
use App\Enums\Equipment;
use App\Livewire\Profile\UpdateBarWeights;
use App\Livewire\Sessions\Run;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Support\PlateCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the plates per side, from heavy to light', function () {
    $bar = new PlateCalculator(20);

    expect($bar->perSide(100))->toBe(['plates' => [25.0, 15.0], 'rest' => 0.0])
        ->and($bar->perSide(62.5))->toBe(['plates' => [20.0, 1.25], 'rest' => 0.0])
        ->and($bar->perSide(20))->toBe(['plates' => [], 'rest' => 0.0])
        ->and($bar->perSide(21))->toBe(['plates' => [], 'rest' => 0.5])   // 0,5 kg per kant past niet
        ->and($bar->perSide(15))->toBeNull()
        ->and((new PlateCalculator(0))->perSide(90))->toBe(['plates' => [25.0, 20.0], 'rest' => 0.0]);
});

test('only exercises with a bar or loaded plates get the calculator', function () {
    $make = fn (string $name, Equipment $equipment) => Exercise::factory()->make(['name' => $name, 'equipment' => $equipment]);

    expect(PlateCalculator::for($make('Bench Press (Barbell)', Equipment::Barbell))->bar)->toBe(20.0)
        ->and(PlateCalculator::for($make('EZ Bar Biceps Curl', Equipment::Barbell))->bar)->toBe(10.0)
        ->and(PlateCalculator::for($make('Leg Press Plated', Equipment::Plate))->bar)->toBe(0.0)
        ->and(PlateCalculator::for($make('Landmine Row', Equipment::Barbell)))->toBeNull()
        ->and(PlateCalculator::for($make('Plate Curl', Equipment::Plate)))->toBeNull()
        ->and(PlateCalculator::for($make('Leg Extension (Machine)', Equipment::Machine)))->toBeNull();   // pen in de stapel
});

test('the training screen shows the plates for the open sets', function () {
    $user = User::factory()->create(['locale' => 'nl']);
    $bench = Exercise::factory()->create(['name' => 'Bench Press (Barbell)', 'equipment' => Equipment::Barbell]);
    $machine = Exercise::factory()->create(['name' => 'Chest Press (Machine)', 'equipment' => Equipment::Machine]);
    $workout = Workout::factory()->for($user)->create();
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $bench->id, 'position' => 0, 'target_sets' => 2, 'target_reps' => 8, 'target_weight' => 60]);
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $machine->id, 'position' => 1, 'target_sets' => 2, 'target_reps' => 8, 'target_weight' => 50]);
    $session = app(StartWorkoutSession::class)->handle($user, $workout);

    $html = Livewire::actingAs($user)->test(Run::class, ['session' => $session])
        ->assertSeeText('Schijven per kant (stang 20 kg)')
        ->assertSeeHtml('>20</span>')
        ->html();

    // Alleen bij de bankdruk, niet bij de machine met een pen in de stapel
    expect(substr_count($html, 'Schijven per kant'))->toBe(1);
});

test('the bar weights can be changed in the profile', function () {
    $user = User::factory()->create(['locale' => 'nl']);
    $bench = Exercise::factory()->make(['name' => 'Bench Press (Barbell)', 'equipment' => Equipment::Barbell]);
    $curl = Exercise::factory()->make(['name' => 'EZ Bar Biceps Curl', 'equipment' => Equipment::Barbell]);

    Livewire::actingAs($user)->test(UpdateBarWeights::class)
        ->set('weights.bar', '15')
        ->set('weights.ez_bar', '7,5')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->bar_weights)->toBe(['bar' => 15, 'ez_bar' => 7.5])
        ->and(PlateCalculator::for($bench, $user->fresh())->bar)->toBe(15.0)
        ->and(PlateCalculator::for($curl, $user->fresh())->bar)->toBe(7.5);

    // Leeg = weer de standaard; onzin wordt geweigerd
    Livewire::actingAs($user)->test(UpdateBarWeights::class)
        ->assertSet('weights.bar', '15')
        ->set('weights.bar', '')->set('weights.ez_bar', '')->call('save');
    expect($user->fresh()->bar_weights)->toBeNull();

    Livewire::actingAs($user)->test(UpdateBarWeights::class)->set('weights.trap_bar', '80')->call('save')->assertHasErrors('weights.trap_bar');
});

test('the migration adds and removes the column', function () {
    $migration = require database_path('migrations/2026_11_17_200000_add_bar_weights_to_users_table.php');

    $migration->down();
    expect(Schema::hasColumn('users', 'bar_weights'))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumn('users', 'bar_weights'))->toBeTrue();
});
