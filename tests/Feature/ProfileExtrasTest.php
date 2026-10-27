<?php

use App\Livewire\Dashboard;
use App\Livewire\Profile\BodyMeasurements;
use App\Livewire\Profile\UpdateRestTime;
use App\Livewire\Profile\UpdateWeeklyGoal;
use App\Models\Exercise;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Support\Registration;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('body measurements are saved per day and summarised', function () {
    $user = User::factory()->create();
    $user->bodyMeasurements()->create(['measured_on' => today()->subMonth(), 'waist_cm' => 98, 'chest_cm' => 110]);

    Livewire::actingAs($user)->test(BodyMeasurements::class)
        ->call('save')
        ->assertHasErrors('values')
        ->set('values.waist_cm', '95,5')
        ->call('save')
        ->assertHasNoErrors()
        ->set('values.arm_cm', '38')
        ->call('save')
        ->assertSee('95,5 cm')
        ->assertSee('-2,5 cm sinds')
        ->assertSee('Taille');

    $today = $user->bodyMeasurements()->whereDate('measured_on', today())->sole();
    expect((float) $today->waist_cm)->toBe(95.5)
        ->and((float) $today->arm_cm)->toBe(38.0)
        ->and($today->chest_cm)->toBeNull();
});

test('body measurements are validated and only your own can be deleted', function () {
    $user = User::factory()->create();
    $theirs = User::factory()->create()->bodyMeasurements()->create(['measured_on' => today(), 'waist_cm' => 80]);
    $mine = $user->bodyMeasurements()->create(['measured_on' => today(), 'waist_cm' => 90]);

    $component = Livewire::actingAs($user)->test(BodyMeasurements::class)
        ->set('values.waist_cm', '5')
        ->call('save')
        ->assertHasErrors('values.waist_cm')
        ->call('delete', $mine->id);

    expect($mine->fresh())->toBeNull();

    try {
        $component->call('delete', $theirs->id);
    } catch (ModelNotFoundException) {
    }
    expect($theirs->fresh())->not->toBeNull();
});

test('a weekly goal in trainings shows on the dashboard', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(UpdateWeeklyGoal::class)
        ->set('hours', '')
        ->set('sessions', '20')
        ->call('save')
        ->assertHasErrors('sessions')
        ->set('sessions', '3')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->refresh()->weekly_goal_sessions)->toBe(3)
        ->and($user->weekly_goal_minutes)->toBeNull();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('/ 3')
        ->assertSee('Nog 3 te gaan');
});

test('your own rest time is used for new exercises', function () {
    Setting::put('default_rest_seconds', 75);
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    expect(WorkoutExercise::defaultsFor($exercise, $user)['rest_seconds'])->toBe(75);

    Livewire::actingAs($user)->test(UpdateRestTime::class)
        ->assertSee('75 sec')
        ->set('seconds', '5')
        ->call('save')
        ->assertHasErrors('seconds')
        ->set('seconds', '120')
        ->call('save')
        ->assertHasNoErrors();

    expect(WorkoutExercise::defaultsFor($exercise, $user->refresh())['rest_seconds'])->toBe(120)
        ->and(WorkoutExercise::defaultRestSeconds())->toBe(75);
});

test('the register form checks if a username is free', function () {
    Registration::open(true);
    User::factory()->create(['username' => 'janjansen']);

    $this->getJson(route('register.username', ['username' => 'janjansen']))
        ->assertOk()->assertJson(['available' => false, 'message' => 'Deze gebruikersnaam is al bezet.']);
    $this->getJson(route('register.username', ['username' => 'jan-2']))
        ->assertJson(['available' => true]);
    $this->getJson(route('register.username', ['username' => 'jan jansen']))
        ->assertJson(['available' => false]);

    Registration::open(false);
    $this->getJson(route('register.username', ['username' => 'iemand']))->assertNotFound();
});
