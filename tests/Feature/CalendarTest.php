<?php

use App\Actions\StartWorkoutSession;
use App\Livewire\Calendar;
use App\Livewire\Dashboard;
use App\Models\Exercise;
use App\Models\ScheduledWorkout;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00')); // donderdag
    $this->user = User::factory()->create();
    $this->workout = Workout::factory()->for($this->user)->create(['name' => 'Push', 'color' => '#0e9f8a']);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => Exercise::factory()->create()->id]);
});

test('the calendar shows trainings and planned workouts of the month', function () {
    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    $session->finish();
    ScheduledWorkout::create(['user_id' => $this->user->id, 'workout_id' => $this->workout->id, 'scheduled_on' => '2026-10-20']);

    Livewire::actingAs($this->user)->test(Calendar::class)
        ->assertSet('month', '2026-10')
        ->assertSet('day', '2026-10-15')
        ->assertSee(['Oktober 2026', 'Donderdag 15 oktober', 'Push', route('sessions.summary', $session)])
        ->call('select', '2026-10-20')
        ->assertSee(['Dinsdag 20 oktober', 'Over 5 dagen', 'Gepland'])
        ->call('nextMonth')->assertSet('month', '2026-11')->assertSee('November 2026');
});

test('a workout can be planned, also every week', function () {
    Livewire::actingAs($this->user)->test(Calendar::class)
        ->call('select', '2026-10-16')
        ->call('openPlanning')
        ->assertSet('workout_id', $this->workout->id)
        ->set('repeat', 4)->set('notes', 'Rustig aan')
        ->call('plan')->assertHasNoErrors();

    expect(ScheduledWorkout::orderBy('scheduled_on')->pluck('scheduled_on')->map->toDateString()->all())
        ->toBe(['2026-10-16', '2026-10-23', '2026-10-30', '2026-11-06'])
        ->and(ScheduledWorkout::first()->notes)->toBe('Rustig aan');
});

test('only own active workouts can be planned', function () {
    $other = Workout::factory()->create();
    $archived = Workout::factory()->for($this->user)->create(['archived_at' => now()]);

    Livewire::actingAs($this->user)->test(Calendar::class)
        ->set('workout_id', $other->id)->call('plan')->assertHasErrors('workout_id')
        ->set('workout_id', $archived->id)->call('plan')->assertHasErrors('workout_id');

    expect(ScheduledWorkout::count())->toBe(0);
});

test('starting a workout marks todays plan as done', function () {
    $planned = ScheduledWorkout::create(['user_id' => $this->user->id, 'workout_id' => $this->workout->id, 'scheduled_on' => today()]);

    Livewire::actingAs($this->user)->test(Calendar::class)
        ->call('start', $planned->id)
        ->assertRedirect(route('sessions.run', WorkoutSession::first()));

    expect($planned->fresh()->workout_session_id)->toBe(WorkoutSession::first()->id);

    // Ook als je de workout gewoon vanaf de workoutpagina start
    WorkoutSession::first()->finish();
    $again = ScheduledWorkout::create(['user_id' => $this->user->id, 'workout_id' => $this->workout->id, 'scheduled_on' => today()]);
    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    expect($again->fresh()->workout_session_id)->toBe($session->id);
});

test('a missed plan is shown and can be removed', function () {
    $planned = ScheduledWorkout::create(['user_id' => $this->user->id, 'workout_id' => $this->workout->id, 'scheduled_on' => '2026-10-13']);
    expect($planned->isMissed())->toBeTrue();

    Livewire::actingAs($this->user)->test(Calendar::class, ['day' => '2026-10-13'])
        ->assertSee('Gemist')
        ->call('confirmDelete', $planned->id)->assertSet('confirmingDeletion', true)
        ->call('deleteConfirmed');

    expect(ScheduledWorkout::count())->toBe(0);
});

test('plans of other users are off limits', function () {
    $planned = ScheduledWorkout::create(['user_id' => User::factory()->create()->id, 'workout_id' => Workout::factory()->create()->id, 'scheduled_on' => today()]);

    Livewire::actingAs($this->user)->test(Calendar::class)->call('confirmDelete', $planned->id)->assertNotFound();
    Livewire::actingAs($this->user)->test(Calendar::class)->call('start', $planned->id)->assertNotFound();
});

test('the dashboard shows the next planned workout', function () {
    Livewire::actingAs($this->user)->test(Dashboard::class)->assertSee('Niets gepland');

    ScheduledWorkout::create(['user_id' => $this->user->id, 'workout_id' => $this->workout->id, 'scheduled_on' => '2026-10-16']);
    Livewire::actingAs($this->user)->test(Dashboard::class)->assertSee(['Volgende workout', 'Push', 'morgen']);
});
