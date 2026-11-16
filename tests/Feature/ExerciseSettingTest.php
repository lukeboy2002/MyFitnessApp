<?php

use App\Actions\DeleteUserAccount;
use App\Actions\StartWorkoutSession;
use App\Livewire\Exercises\Setting;
use App\Models\Exercise;
use App\Models\ExerciseSetting;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['locale' => 'nl']);
    $this->press = Exercise::factory()->create(['name' => 'Chest Press', 'user_id' => null]);
});

test('a user saves a machine setting per exercise; empty removes it', function () {
    Livewire::actingAs($this->user)->test(Setting::class, ['exercise' => $this->press])
        ->assertSee('Instelling toevoegen')
        ->set('body', '  stoel 4, rugleuning 2 ')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('setting-saved')
        ->assertSet('body', 'stoel 4, rugleuning 2');

    expect($this->user->exerciseSettings()->sole())
        ->exercise_id->toBe($this->press->id)
        ->body->toBe('stoel 4, rugleuning 2');

    // Wijzigen overschrijft, er blijft er één
    Livewire::actingAs($this->user)->test(Setting::class, ['exercise' => $this->press])
        ->assertSet('body', 'stoel 4, rugleuning 2')
        ->set('body', 'stoel 5')->call('save');
    expect($this->user->exerciseSettings()->sole()->body)->toBe('stoel 5');

    Livewire::actingAs($this->user)->test(Setting::class, ['exercise' => $this->press])
        ->set('body', '')->call('save')->assertHasNoErrors();
    expect(ExerciseSetting::count())->toBe(0);
});

test('the setting is personal and has a maximum length', function () {
    $other = User::factory()->create();
    $this->user->exerciseSettings()->create(['exercise_id' => $this->press->id, 'body' => 'stoel 4']);

    Livewire::actingAs($other)->test(Setting::class, ['exercise' => $this->press])
        ->assertSet('body', '')
        ->set('body', str_repeat('x', ExerciseSetting::MAX + 1))->call('save')
        ->assertHasErrors('body');

    // Een eigen oefening van iemand anders kun je niet openen
    $own = Exercise::factory()->create(['user_id' => $other->id]);
    Livewire::actingAs($this->user)->test(Setting::class, ['exercise' => $own])->assertForbidden();
});

test('the setting is shown during the training and on the exercise page', function () {
    $this->user->exerciseSettings()->create(['exercise_id' => $this->press->id, 'body' => 'stoel 4, rugleuning 2']);
    $workout = Workout::factory()->for($this->user)->create();
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $this->press->id]);
    $session = app(StartWorkoutSession::class)->handle($this->user, $workout);

    $this->actingAs($this->user)->get(route('sessions.run', $session))->assertOk()->assertSee('stoel 4, rugleuning 2');
    $this->actingAs($this->user)->get(route('exercises.show', $this->press))->assertOk()->assertSee('Apparaatinstelling')->assertSee('stoel 4, rugleuning 2');
});

test('deleting an account removes its settings', function () {
    $this->user->exerciseSettings()->create(['exercise_id' => $this->press->id, 'body' => 'stoel 4']);

    app(DeleteUserAccount::class)->handle($this->user);

    expect(ExerciseSetting::count())->toBe(0);
});

test('the migration creates and removes the table', function () {
    $migration = require database_path('migrations/2026_11_16_230000_create_exercise_settings_table.php');

    $migration->down();
    expect(Schema::hasTable('exercise_settings'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('exercise_settings'))->toBeTrue();
});
