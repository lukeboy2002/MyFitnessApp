<?php

use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Livewire\Exercises\Form;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create();
    $this->exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'type' => ExerciseType::Strength]);
    $this->exercise->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'chest')->value('id'));
});

test('an unused exercise can still change its type', function () {
    expect($this->exercise->isTypeLocked())->toBeFalse();

    Livewire::actingAs($this->user)->test(Form::class, ['exercise' => $this->exercise])
        ->assertSet('typeLocked', false)
        ->set('type', 'bodyweight')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->exercise->fresh()->type)->toBe(ExerciseType::Bodyweight);
});

test('the type is locked once the exercise is in a workout', function () {
    WorkoutExercise::factory()->create(['exercise_id' => $this->exercise->id]);

    expect($this->exercise->isTypeLocked())->toBeTrue();

    Livewire::actingAs($this->user)->test(Form::class, ['exercise' => $this->exercise])
        ->assertSet('typeLocked', true)
        ->assertSee('Het type ligt vast')
        ->set('type', 'cardio')
        ->assertSet('type', 'strength')
        ->set('name', 'Nieuwe naam')
        ->call('save')
        ->assertHasNoErrors();

    $exercise = $this->exercise->fresh();
    expect($exercise->type)->toBe(ExerciseType::Strength);
    expect($exercise->name)->toBe('Nieuwe naam');
});

test('the type is locked once the exercise was trained', function () {
    WorkoutSession::create([
        'user_id' => $this->user->id, 'name' => 'Push', 'color' => '#00468c', 'status' => SessionStatus::Completed,
        'started_at' => now()->subHour(), 'ended_at' => now(), 'duration_seconds' => 3600,
    ])->exercises()->create(['exercise_id' => $this->exercise->id, 'position' => 1]);

    Livewire::actingAs($this->user)->test(Form::class, ['exercise' => $this->exercise])
        ->assertSet('typeLocked', true)
        ->set('type', 'duration')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->exercise->fresh()->type)->toBe(ExerciseType::Strength);
});

test('the lock flag cannot be switched off from the browser', function () {
    WorkoutExercise::factory()->create(['exercise_id' => $this->exercise->id]);

    expect(fn () => Livewire::actingAs($this->user)->test(Form::class, ['exercise' => $this->exercise])->set('typeLocked', false))
        ->toThrow(Exception::class);
});
