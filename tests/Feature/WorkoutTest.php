<?php

use App\Enums\CardioMetric;
use App\Livewire\Exercises\Index as ExercisesIndex;
use App\Livewire\Workouts\Form;
use App\Livewire\Workouts\Index;
use App\Livewire\Workouts\Show;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Database\Seeders\BodyPartSeeder;
use Database\Seeders\ExerciseSeeder;
use Database\Seeders\WorkoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create();
});

function workoutExercise(string $type = 'strength', array $attributes = []): Exercise
{
    $exercise = Exercise::factory()->{$type === 'cardio' ? 'cardio' : 'state'}([])->create($attributes);
    $exercise->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', $type === 'cardio' ? 'cardio' : 'chest')->value('id'));

    return $exercise;
}

test('the workout pages render', function () {
    $workout = Workout::factory()->for($this->user)->create();
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => workoutExercise()->id]);

    $this->actingAs($this->user)->get(route('workouts.index'))->assertOk()->assertSee($workout->name);
    $this->actingAs($this->user)->get(route('workouts.create'))->assertOk();
    $this->actingAs($this->user)->get(route('workouts.show', $workout))->assertOk()->assertSee('3 × 10');
    $this->actingAs($this->user)->get(route('workouts.edit', $workout))->assertOk();
});

test('a workout can be created with exercises and targets per type', function () {
    $press = workoutExercise('strength', ['name' => 'Chest Press', 'default_weight' => 40]);
    $bike = workoutExercise('cardio', ['name' => 'Hometrainer']);

    $component = Livewire::actingAs($this->user)->test(Form::class)
        ->set('name', 'Push')
        ->call('addExercise', $press->id)
        ->call('addExercise', $bike->id)
        ->assertSet('items.0.sets', 3)
        ->assertSet('items.0.weight', '40')
        ->assertSet('items.0.rest', 90)
        ->assertSet('items.1.minutes', 20)
        ->set('items.0.weight', '42,5')
        ->set('items.1.metric', 'speed')
        ->set('items.1.metric_value', '10,5')
        ->call('save')
        ->assertHasNoErrors();

    $workout = Workout::firstWhere('name', 'Push');
    $component->assertRedirect(route('workouts.show', $workout));

    [$first, $second] = $workout->exercises()->with('exercise')->get()->all();
    expect($first->exercise_id)->toBe($press->id)
        ->and($first->target_weight)->toBe('42.50')
        ->and($first->targetSummary())->toBe('3 × 10 · 42,5 kg')
        ->and($second->target_duration_seconds)->toBe(1200)
        ->and($second->target_metric_value)->toBe('10.5')
        ->and($second->target_sets)->toBeNull()
        ->and($second->targetSummary())->toBe('20 min · 10,5 km/h');

    // Zonder gekozen spiergroepen: de primaire spiergroepen van de oefeningen
    expect($workout->muscleGroups->pluck('slug')->sort()->values()->all())->toBe(['cardio', 'chest']);
});

test('a workout needs a name and at least one exercise', function () {
    Livewire::actingAs($this->user)->test(Form::class)
        ->call('save')
        ->assertHasErrors(['name', 'items']);
});

test('exercises can be reordered, removed and edited without losing their ids', function () {
    $workout = Workout::factory()->for($this->user)->create();
    $a = WorkoutExercise::factory()->for($workout)->create(['exercise_id' => workoutExercise()->id, 'position' => 0]);
    $b = WorkoutExercise::factory()->for($workout)->create(['exercise_id' => workoutExercise()->id, 'position' => 1]);
    $c = WorkoutExercise::factory()->for($workout)->create(['exercise_id' => workoutExercise()->id, 'position' => 2]);

    $component = Livewire::actingAs($this->user)->test(Form::class, ['workout' => $workout]);
    $keys = collect($component->get('items'))->pluck('key');

    $component->call('sortItem', $keys[2], 0)   // c naar boven
        ->call('removeItem', $keys[1])          // b weg
        ->set('items.1.reps', 12)               // a staat nu op plek 2
        ->call('save')
        ->assertHasNoErrors();

    expect($workout->exercises()->pluck('id')->all())->toBe([$c->id, $a->id])
        ->and($a->fresh()->target_reps)->toBe(12)
        ->and($b->fresh())->toBeNull();
});

test('other users cannot see or change my workouts', function () {
    $workout = Workout::factory()->create();

    $this->actingAs($this->user)->get(route('workouts.show', $workout))->assertForbidden();
    $this->actingAs($this->user)->get(route('workouts.edit', $workout))->assertForbidden();
    Livewire::actingAs($this->user)->test(Index::class)->assertDontSee($workout->name)
        ->call('archive', $workout->id)->assertNotFound();
});

test('workouts can be archived, restored, duplicated and deleted after confirmation', function () {
    $workout = Workout::factory()->for($this->user)->create(['name' => 'Pull']);
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => workoutExercise()->id]);

    $component = Livewire::actingAs($this->user)->test(Index::class)
        ->call('archive', $workout->id)
        ->assertSet('message', 'Pull staat in het archief.');
    expect($workout->fresh()->isArchived())->toBeTrue();

    $component->set('showArchived', true)->assertSee('Pull')
        ->call('restore', $workout->id);
    expect($workout->fresh()->isArchived())->toBeFalse();

    Livewire::actingAs($this->user)->test(Index::class)->call('duplicate', $workout->id);
    $copy = Workout::firstWhere('name', 'Pull (kopie)');
    expect($copy->exercises)->toHaveCount(1)->and($copy->slug)->not->toBe($workout->slug);

    Livewire::actingAs($this->user)->test(Index::class)
        ->call('confirmDelete', $workout->id)
        ->assertSet('deletingName', 'Pull')
        ->call('deleteConfirmed');
    expect($workout->fresh())->toBeNull()
        ->and(WorkoutExercise::where('workout_id', $workout->id)->count())->toBe(0);
});

test('the show page can archive the workout', function () {
    $workout = Workout::factory()->for($this->user)->create();

    Livewire::actingAs($this->user)->test(Show::class, ['workout' => $workout])->call('archive');
    expect($workout->fresh()->isArchived())->toBeTrue();
});

test('an exercise that is used in a workout cannot be deleted', function () {
    $exercise = workoutExercise('strength', ['user_id' => $this->user->id]);
    WorkoutExercise::factory()->for(Workout::factory()->for($this->user))->create(['exercise_id' => $exercise->id]);

    Livewire::actingAs($this->user)->test(ExercisesIndex::class)
        ->call('delete', $exercise->id)
        ->assertSet('error', fn ($error) => str_contains($error, 'workout'));

    expect($exercise->fresh())->not->toBeNull();
});

test('the picker only offers active exercises that I can see', function () {
    $mine = workoutExercise('strength', ['user_id' => $this->user->id, 'name' => 'Mijn oefening']);
    $other = workoutExercise('strength', ['user_id' => User::factory()->create()->id, 'name' => 'Andermans oefening']);
    workoutExercise('strength', ['name' => 'Oude oefening', 'archived_at' => now()]);

    $component = Livewire::actingAs($this->user)->test(Form::class)->set('pickerSearch', 'oefening');
    expect($component->instance()->pickerExercises->pluck('name')->all())->toBe(['Mijn oefening']);

    $component->call('addExercise', $other->id)->assertNotFound();
});

test('the workout seeder creates example workouts', function () {
    $this->seed(ExerciseSeeder::class);
    $this->seed(WorkoutSeeder::class);
    $this->seed(WorkoutSeeder::class);

    expect(Workout::count())->toBe(3)
        ->and(Workout::firstWhere('name', 'Benen + cardio')->exercises)->toHaveCount(6)
        ->and(Workout::firstWhere('name', 'Push')->minutes())->toBeGreaterThan(20);
});

test('a cardio exercise brings its machine setting into the workout', function () {
    $bike = workoutExercise('cardio', ['name' => 'Hometrainer', 'default_metric' => 'resistance', 'default_metric_value' => 8]);

    Livewire::actingAs($this->user)->test(Form::class)
        ->set('name', 'Fietsen')
        ->call('addExercise', $bike->id)
        ->assertSet('items.0.metric', 'resistance')
        ->assertSet('items.0.metric_value', '8')
        ->call('save')
        ->assertHasNoErrors();

    expect(WorkoutExercise::with('exercise')->first()->targetSummary())->toBe('20 min · weerstand 8');
});

test('the seeder gives cardio machines a setting', function () {
    $this->seed(ExerciseSeeder::class);

    expect(Exercise::where('slug', 'treadmill')->first()->default_metric)->toBe(CardioMetric::Speed)
        ->and(Exercise::where('slug', 'cross-trainer')->first()->default_metric)->toBe(CardioMetric::Level)
        ->and(Exercise::where('slug', 'jump-rope')->first()->default_metric)->toBeNull();
});

test('the exercise picker can show only own exercises', function () {
    workoutExercise('strength', ['name' => 'Standaard Press']);

    Livewire::actingAs($this->user)->test(Form::class)
        ->call('openPicker')->assertDontSee('Eigen oefeningen');

    workoutExercise('strength', ['name' => 'Mijn Curl', 'user_id' => $this->user->id]);

    Livewire::actingAs($this->user)->test(Form::class)
        ->call('openPicker')
        ->assertSee(['Eigen oefeningen', 'Standaard Press', 'Mijn Curl'])
        ->set('pickerOwn', true)
        ->assertSee('Mijn Curl')->assertDontSee('Standaard Press')
        ->call('openPicker')->assertSet('pickerOwn', false);
});
