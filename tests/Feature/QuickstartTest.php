<?php

use App\Livewire\Sessions\Quickstart;
use App\Livewire\Sessions\Run;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\WorkoutSession;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create();

    $this->press = Exercise::factory()->create(['name' => 'Chest Press', 'default_sets' => 3, 'default_reps' => 12, 'default_weight' => 30]);
    $this->press->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'chest')->value('id'));
    $this->bike = Exercise::factory()->cardio()->create(['name' => 'Hometrainer', 'default_duration_seconds' => 900]);
    $this->bike->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'cardio')->value('id'));
});

test('the quickstart page starts a free training', function () {
    $this->actingAs($this->user)->get(route('quickstart'))->assertOk()
        ->assertSee(['Free Training', WorkoutSession::QUICKSTART_DESCRIPTION, 'Start Free Training']);

    Livewire::actingAs($this->user)->test(Quickstart::class)
        ->call('start')
        ->assertRedirect(route('sessions.run', WorkoutSession::first()));

    $session = WorkoutSession::first();
    expect($session->name)->toBe('Free Training')
        ->and($session->color)->toBe(WorkoutSession::QUICKSTART_COLOR)
        ->and($session->workout_id)->toBeNull()
        ->and($session->isQuickstart())->toBeTrue()
        ->and($session->exercises)->toBeEmpty();

    $this->actingAs($this->user)->get(route('sessions.run', $session))
        ->assertSee([WorkoutSession::QUICKSTART_DESCRIPTION, 'Nog geen oefeningen']);

    // Nog een keer: je komt in dezelfde training terecht
    Livewire::actingAs($this->user)->test(Quickstart::class)->assertSee('Verder met training')->call('start');
    expect(WorkoutSession::count())->toBe(1);
});

test('exercises can be added with targets during a training', function () {
    Livewire::actingAs($this->user)->test(Quickstart::class)->call('start');
    $session = WorkoutSession::first();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->call('openAdding')
        ->assertSet('showPicker', true)
        ->call('pick', $this->press->id)
        ->assertSet('adding.sets', 3)
        ->assertSet('adding.reps', 12)
        ->assertSet('adding.weight', '30')
        ->set('adding.sets', 4)
        ->set('adding.weight', '32,5')
        ->set('adding.reps', 500)
        ->call('addExercise')
        ->assertHasErrors('adding.reps')
        ->set('adding.reps', 10)
        ->call('addExercise')
        ->assertHasNoErrors()
        ->assertSet('showPicker', false)
        ->assertDispatched('exercise-added')
        ->call('openAdding')
        ->call('pick', $this->bike->id)
        ->assertSet('adding.minutes', 15)
        ->set('adding.metric', 'level')
        ->set('adding.metric_value', '9')
        ->call('addExercise')
        ->assertSee(['Chest Press', 'Hometrainer']);

    [$press, $bike] = $session->exercises()->with('sets', 'cardio')->get()->all();
    expect($press->position)->toBe(0)
        ->and($press->target_sets)->toBe(4)
        ->and($press->sets)->toHaveCount(4)
        ->and($press->sets->first()->weight)->toBe('32.50')
        ->and($press->sets->first()->reps)->toBe(10)
        ->and($bike->position)->toBe(1)
        ->and($bike->cardio->duration_seconds)->toBe(900)
        ->and($bike->cardio->metric_value)->toBe('9.0');
});

test('cancelling a free training goes back to quickstart', function () {
    Livewire::actingAs($this->user)->test(Quickstart::class)->call('start');

    Livewire::actingAs($this->user)->test(Run::class, ['session' => WorkoutSession::first()])
        ->call('cancel')
        ->assertRedirect(route('quickstart'));
});
