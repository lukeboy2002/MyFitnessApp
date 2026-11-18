<?php

use App\Actions\AdoptWorkout;
use App\Actions\DeleteUserAccount;
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
use Database\Seeders\StandardWorkoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create();
    $this->admin = User::factory()->create(['is_admin' => true]);

    $this->exercise = Exercise::factory()->create(['user_id' => null, 'name' => 'Bicep Curl (Barbell)']);
    $this->exercise->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'biceps')->value('id'));

    $this->ready = Workout::factory()->create(['user_id' => null, 'name' => 'Armen met barbell', 'slug' => 'armen-met-barbell']);
    WorkoutExercise::factory()->for($this->ready)->create(['exercise_id' => $this->exercise->id, 'target_sets' => 3, 'target_reps' => 10]);
    $this->ready->muscleGroups()->sync(MuscleGroup::whereNull('user_id')->where('slug', 'biceps')->value('id'));
});

test('everyone sees the ready-made workouts in their own tab', function () {
    $own = Workout::factory()->for($this->user)->create(['name' => 'Mijn push']);

    Livewire::actingAs($this->user)->test(Index::class)
        ->assertSee('Mijn push')->assertDontSee('Armen met barbell')
        ->call('showTab', 'ready')
        ->assertSee('Armen met barbell')->assertDontSee('Mijn push')
        ->assertSee(__('Add to my workouts'))
        ->assertSet('counts.ready', 1);

    $this->actingAs($this->user)->get(route('workouts.show', $this->ready))->assertOk()->assertSee(__('Add to my workouts'));
});

test('adding a ready-made workout makes an own copy that can be changed', function () {
    Livewire::actingAs($this->user)->test(Index::class, ['showReady' => true])
        ->call('adopt', $this->ready->id)
        ->assertRedirect();

    $copy = Workout::where('user_id', $this->user->id)->sole();
    expect($copy->name)->toBe('Armen met barbell')
        ->and($copy->source_workout_id)->toBe($this->ready->id)
        ->and($copy->exercises()->first()->target_reps)->toBe(10)
        ->and($copy->muscleGroups()->count())->toBe(1);

    // De kopie is van jou: wijzigen en starten mag, de standaardworkout blijft gelijk
    Livewire::actingAs($this->user)->test(Form::class, ['workout' => $copy])->set('name', 'Mijn armen')->call('save')->assertHasNoErrors();
    expect($this->ready->fresh()->name)->toBe('Armen met barbell');

    Livewire::actingAs($this->user)->test(Show::class, ['workout' => $copy])->call('start')->assertRedirect();

    Livewire::actingAs($this->user)->test(Index::class, ['showReady' => true])
        ->assertSee(__('Already added'))->assertSee(__('Add again'));
});

test('a user cannot change, start or delete a ready-made workout', function () {
    $this->actingAs($this->user)->get(route('workouts.edit', $this->ready))->assertForbidden();

    Livewire::actingAs($this->user)->test(Show::class, ['workout' => $this->ready])
        ->assertDontSee(__('Start workout'))
        ->call('start')->assertForbidden();

    Livewire::actingAs($this->user)->test(Index::class)->call('delete', $this->ready->id)->assertForbidden();
    Livewire::actingAs($this->user)->test(Index::class)->call('archive', $this->ready->id)->assertForbidden();

    expect($this->ready->fresh())->not->toBeNull();
});

test('a user cannot see or adopt another users workout', function () {
    $other = Workout::factory()->for(User::factory())->create();

    $this->actingAs($this->user)->get(route('workouts.show', $other))->assertForbidden();
    Livewire::actingAs($this->user)->test(Index::class)->call('adopt', $other->id)->assertNotFound();
});

test('archived ready-made workouts are hidden for users and cannot be adopted', function () {
    $this->ready->update(['archived_at' => now()]);

    Livewire::actingAs($this->user)->test(Index::class, ['showReady' => true])->assertDontSee('Armen met barbell');
    Livewire::actingAs($this->user)->test(Index::class)->call('adopt', $this->ready->id)->assertForbidden();
    Livewire::actingAs($this->admin)->test(Index::class, ['showReady' => true])->assertSee('Armen met barbell');
});

test('the admin creates a ready-made workout with standard exercises only', function () {
    $own = Exercise::factory()->create(['user_id' => $this->admin->id]);

    $this->actingAs($this->user)->get(route('workouts.create', ['standaard' => 1]))->assertOk()->assertDontSee(__('New ready-made workout'));

    $form = Livewire::actingAs($this->admin)->withQueryParams(['standaard' => 1])->test(Form::class)
        ->assertSet('standard', true)
        ->assertSee(__('New ready-made workout'));

    // Eigen oefeningen staan niet in de kiezer en worden geweigerd
    expect($form->get('pickerOwnCount'))->toBe(0)
        ->and($form->get('pickerExercises')->pluck('id'))->not->toContain($own->id);
    Livewire::actingAs($this->admin)->withQueryParams(['standaard' => 1])->test(Form::class)
        ->call('addExercise', $own->id)->assertNotFound();

    $form->set('name', 'Benen machines')
        ->call('addExercise', $this->exercise->id)
        ->call('save')
        ->assertHasNoErrors();

    $workout = Workout::where('name', 'Benen machines')->sole();
    expect($workout->isStandard())->toBeTrue();

    // Gebruikers kunnen standaard niet aanzetten
    Livewire::actingAs($this->user)->withQueryParams(['standaard' => 1])->test(Form::class)->assertSet('standard', false);
});

test('the admin edits a ready-made workout and keeps its slug', function () {
    Livewire::actingAs($this->admin)->test(Form::class, ['workout' => $this->ready])
        ->assertSet('standard', true)
        ->set('name', 'Armen (barbell)')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->ready->fresh())->name->toBe('Armen (barbell)')->slug->toBe('armen-met-barbell')->user_id->toBeNull();
});

test('the admin copies an own workout as ready-made, but only with standard exercises', function () {
    $mine = Workout::factory()->for($this->admin)->create(['name' => 'Push']);
    WorkoutExercise::factory()->for($mine)->create(['exercise_id' => $this->exercise->id]);

    Livewire::actingAs($this->admin)->test(Index::class)->call('copyAsStandard', $mine->id)->assertRedirect();
    expect(Workout::whereNull('user_id')->where('name', 'Push')->sole()->source_workout_id)->toBeNull();

    $withOwn = Workout::factory()->for($this->admin)->create(['name' => 'Eigen']);
    WorkoutExercise::factory()->for($withOwn)->create(['exercise_id' => Exercise::factory()->create(['user_id' => $this->admin->id])->id]);

    Livewire::actingAs($this->admin)->test(Index::class)->call('copyAsStandard', $withOwn->id)->assertHasErrors('standard');
    expect(Workout::whereNull('user_id')->where('name', 'Eigen')->exists())->toBeFalse();

    Livewire::actingAs($this->user)->test(Index::class)->call('copyAsStandard', Workout::factory()->for($this->user)->create()->id)->assertForbidden();
});

test('the ready-made workouts can be filtered by body part and equipment', function () {
    $legs = Workout::factory()->create(['user_id' => null, 'name' => 'Benen machines']);
    $legs->muscleGroups()->sync(MuscleGroup::whereNull('user_id')->where('slug', 'quadriceps')->value('id'));
    $arms = MuscleGroup::whereNull('user_id')->where('slug', 'biceps')->first()->body_part_id;

    Livewire::actingAs($this->user)->test(Index::class, ['showReady' => true])
        ->assertSee('Benen machines')
        ->set('bodyPart', $arms)
        ->assertSee('Armen met barbell')->assertDontSee('Benen machines')
        ->set('bodyPart', null)
        ->set('equipmentFilter', $this->exercise->equipment_id)
        ->assertSee('Armen met barbell')->assertDontSee('Benen machines');
});

test('deleting a ready-made workout keeps the copies', function () {
    $copy = app(AdoptWorkout::class)->handle($this->user, $this->ready);

    Livewire::actingAs($this->admin)->test(Index::class)->call('delete', $this->ready->id);

    expect($copy->fresh())->not->toBeNull()->source_workout_id->toBeNull();
});

test('deleting an account keeps the ready-made workouts', function () {
    app(AdoptWorkout::class)->handle($this->user, $this->ready);
    app(DeleteUserAccount::class)->handle($this->user);

    expect(Workout::whereNull('user_id')->count())->toBe(1);
});

test('the seeder makes ten ready-made workouts and leaves changes alone', function () {
    Workout::whereNull('user_id')->delete();
    $this->seed(ExerciseSeeder::class);
    $this->seed(StandardWorkoutSeeder::class);

    expect(Workout::whereNull('user_id')->count())->toBe(10);

    foreach (StandardWorkoutSeeder::WORKOUTS as $name => [, , $targets]) {
        expect(Workout::whereNull('user_id')->where('name', $name)->first()->exercises()->count())->toBe(count($targets), $name);
    }

    Workout::whereNull('user_id')->where('slug', 'schouders')->update(['name' => 'Schouders (aangepast)']);
    $this->seed(StandardWorkoutSeeder::class);

    expect(Workout::whereNull('user_id')->count())->toBe(10)
        ->and(Workout::whereNull('user_id')->where('slug', 'schouders')->value('name'))->toBe('Schouders (aangepast)');
});
