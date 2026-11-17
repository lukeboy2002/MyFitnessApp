<?php

use App\Actions\AddSessionExercise;
use App\Actions\StartWorkoutSession;
use App\Livewire\Sessions\Run;
use App\Livewire\Workouts\Form;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Support\Supersets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['locale' => 'nl']);
    $this->press = Exercise::factory()->create(['name' => 'Leg Press']);
    $this->extension = Exercise::factory()->create(['name' => 'Leg Extension']);
    $this->curl = Exercise::factory()->create(['name' => 'Leg Curl']);
    $this->bike = Exercise::factory()->cardio()->create(['name' => 'Hometrainer']);
});

/** Workout met de vier oefeningen; $linked = posities die aan de volgende gekoppeld zijn */
function supersetWorkout(array $linked = []): Workout
{
    $workout = Workout::factory()->for(test()->user)->create(['name' => 'Benen']);
    foreach ([test()->press, test()->extension, test()->curl, test()->bike] as $position => $exercise) {
        WorkoutExercise::factory()->for($workout)->create([
            'exercise_id' => $exercise->id, 'position' => $position, 'superset_next' => in_array($position, $linked),
            'target_sets' => $exercise->type->usesSets() ? 2 : null, 'target_reps' => 10, 'rest_seconds' => $exercise->type->usesSets() ? 90 : null,
        ]);
    }

    return $workout;
}

test('the rules: pairs only, no cardio, a pair moves together and is never split', function () {
    $order = ['a', 'b', 'c', 'd'];
    $cardio = ['d' => true];

    expect(Supersets::normalize($order, ['a' => true, 'b' => true, 'c' => true], $cardio))
        ->toBe(['a' => true, 'b' => false, 'c' => false, 'd' => false])
        ->and(Supersets::canLink($order, ['a' => true], $cardio, 'b'))->toBeFalse()   // b zit al in een paar
        ->and(Supersets::canLink($order, [], $cardio, 'c'))->toBeFalse()              // d is cardio
        ->and(Supersets::canLink($order, [], $cardio, 'b'))->toBeTrue()
        ->and(Supersets::move($order, ['b' => true], 'c', 0))->toBe(['b', 'c', 'a', 'd'])   // paar b+c samen naar boven
        ->and(Supersets::move($order, ['b' => true], 'a', 1))->toBe(['b', 'c', 'a', 'd'])   // a tussen b en c → erachter
        ->and(Supersets::move($order, ['a' => true], 'a', PHP_INT_MAX))->toBe(['c', 'd', 'a', 'b']);
});

test('in the workout editor two exercises can be linked, unlinked and saved', function () {
    $workout = supersetWorkout();

    $component = Livewire::actingAs($this->user)->test(Form::class, ['workout' => $workout])
        ->assertSee('Superset met volgende');
    $keys = collect($component->get('items'))->pluck('key');

    $component->call('toggleSuperset', $keys[2])            // Leg Curl + Hometrainer (cardio): kan niet
        ->assertSet('items.2.superset', false)
        ->call('toggleSuperset', $keys[1])                  // Leg Extension + Leg Curl
        ->assertSet('items.1.superset', true)
        ->assertSee('Gekoppeld · tik om los te maken')
        ->call('toggleSuperset', $keys[0])                  // Leg Extension zit al in een paar
        ->assertSet('items.0.superset', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($workout->exercises()->pluck('superset_next')->all())->toBe([false, true, false, false]);
    $this->actingAs($this->user)->get(route('workouts.show', $workout))->assertSee('superset met de volgende');

    // Slepen: het paar gaat samen mee; tik op de tweede maakt het paar los
    $component->call('sortItem', $keys[2], 0)
        ->assertSet('items.0.key', $keys[1])->assertSet('items.1.key', $keys[2])->assertSet('items.0.superset', true)
        ->call('toggleSuperset', $keys[2])
        ->assertSet('items.0.superset', false);

    // Kopie van de workout houdt de koppeling
    expect($workout->duplicate('Kopie')->exercises()->pluck('superset_next')->all())->toBe([false, true, false, false]);
});

test('during the training: no rest after the first exercise, rest after the second', function () {
    $session = app(StartWorkoutSession::class)->handle($this->user, supersetWorkout([1]));
    [, $extension, $curl] = $session->exercises()->with('sets')->get()->all();

    expect($extension->superset_next)->toBeTrue();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->assertSee('ronde 1 van 2')
        ->assertSee('geen rust, daarna Leg Curl')
        ->call('toggleSet', $extension->sets[0]->id)
        ->assertNotDispatched('rest-start')
        ->assertDispatched('superset-next', id: $curl->id, set: $curl->sets[0]->id, name: 'Leg Curl', number: 1)
        ->call('toggleSet', $curl->sets[0]->id)
        ->assertDispatched('rest-start', seconds: 90)
        ->assertSee('ronde 2 van 2');
});

test('linking during a quickstart, moving the pair and deleting one of them', function () {
    $session = app(StartWorkoutSession::class)->quickstart($this->user);
    $add = app(AddSessionExercise::class);
    $press = $add->handle($session, $this->press, ['target_sets' => 2]);
    $extension = $add->handle($session, $this->extension, ['target_sets' => 2]);
    $curl = $add->handle($session, $this->curl, ['target_sets' => 2]);

    $component = Livewire::actingAs($this->user)->test(Run::class, ['session' => WorkoutSession::find($session->id)])
        ->call('toggleSuperset', $press->id);
    expect($press->fresh()->superset_next)->toBeTrue();

    // "Later" op de tweede: het hele paar gaat achteraan
    $component->call('moveToEnd', $extension->id);
    expect($session->exercises()->pluck('id')->all())->toBe([$curl->id, $press->id, $extension->id])
        ->and($press->fresh()->superset_next)->toBeTrue();

    // Tweede weg: koppeling vervalt
    $component->call('delete', $extension->id);
    expect($press->fresh()->superset_next)->toBeFalse();
});

test('the migration adds and removes the columns', function () {
    $migration = require database_path('migrations/2026_11_17_190000_add_superset_next_to_exercise_tables.php');

    $migration->down();
    expect(Schema::hasColumn('workout_exercises', 'superset_next'))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumn('session_exercises', 'superset_next'))->toBeTrue();
});
