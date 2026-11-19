<?php

use App\Enums\SessionStatus;
use App\Livewire\Dashboard;
use App\Livewire\Sessions\Run;
use App\Livewire\Workouts\Routine as RoutinePage;
use App\Models\Exercise;
use App\Models\RoutineItem;
use App\Models\ScheduledWorkout;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Support\Routine;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create(['onboarded_at' => now()]);
    $exercise = Exercise::factory()->create();

    // Schema A-B-C
    $this->workouts = collect(['A', 'B', 'C'])->mapWithKeys(function ($name) use ($exercise) {
        $workout = Workout::factory()->for($this->user)->create(['name' => 'Workout '.$name]);
        WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $exercise->id]);

        return [$name => $workout];
    });
});

function routineOf(User $user, string ...$names): void
{
    foreach ($names as $position => $name) {
        RoutineItem::create(['user_id' => $user->id, 'workout_id' => test()->workouts[$name]->id, 'position' => $position]);
    }
}

function currentName(User $user): ?string
{
    return Routine::current($user->fresh())?->workout->name;
}

test('the schedule page adds, sorts, removes and chooses the next workout', function () {
    $this->actingAs($this->user)->get(route('workouts.routine'))->assertOk()->assertSee('Nog geen schema');

    $page = Livewire::actingAs($this->user)->test(RoutinePage::class)
        ->set('workoutId', $this->workouts['A']->id)->call('add')
        ->set('workoutId', $this->workouts['B']->id)->call('add')
        ->set('workoutId', $this->workouts['A']->id)->call('add');   // dezelfde mag vaker

    expect(Routine::items($this->user)->pluck('workout.name')->all())->toBe(['Workout A', 'Workout B', 'Workout A']);

    $last = RoutineItem::orderByDesc('id')->first();
    $page->call('sort', $last->id, 0);
    expect(Routine::items($this->user)->pluck('id')->first())->toBe($last->id);

    $page->call('makeNext', RoutineItem::where('workout_id', $this->workouts['B']->id)->value('id'));
    expect(currentName($this->user))->toBe('Workout B');

    $page->call('remove', $last->id);
    expect(RoutineItem::count())->toBe(2);

    // Workout van een ander toevoegen of andermans plek weghalen kan niet
    $other = Workout::factory()->for(User::factory())->create();
    $page->set('workoutId', $other->id)->call('add')->assertHasErrors('workoutId');
    $foreign = RoutineItem::create(['user_id' => $other->user_id, 'workout_id' => $other->id, 'position' => 0]);
    Livewire::actingAs($this->user)->test(RoutinePage::class)->call('remove', $foreign->id)->assertNotFound();
});

test('the schedule moves on after finishing the workout that is up', function () {
    routineOf($this->user, 'A', 'B', 'C');
    expect(currentName($this->user))->toBe('Workout A');

    // Start vanaf het dashboard en afronden: B is aan de beurt
    Livewire::actingAs($this->user)->test(Dashboard::class)
        ->assertSee(['Workout A', 'Daarna: Workout B'])
        ->call('startRoutine');
    $session = WorkoutSession::sole();
    expect($session->workout_id)->toBe($this->workouts['A']->id);

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])->call('finish');
    expect(currentName($this->user))->toBe('Workout B');

    Livewire::actingAs($this->user)->test(Dashboard::class)->assertSee(['Volgende keer', 'Workout B']);

    // Een andere workout afronden laat het schema staan
    $other = WorkoutSession::create(['user_id' => $this->user->id, 'workout_id' => $this->workouts['C']->id, 'name' => 'C', 'status' => SessionStatus::Completed, 'started_at' => now(), 'ended_at' => now()]);
    Routine::completed($other);
    expect(currentName($this->user))->toBe('Workout B');
});

test('skip and choose on the dashboard, and the schedule wraps around', function () {
    routineOf($this->user, 'A', 'B', 'C');

    $dashboard = Livewire::actingAs($this->user)->test(Dashboard::class)->call('skipRoutine');
    expect(currentName($this->user))->toBe('Workout B');

    $dashboard->call('chooseRoutine', RoutineItem::where('workout_id', $this->workouts['C']->id)->value('id'));
    expect(currentName($this->user))->toBe('Workout C');

    $dashboard->call('skipRoutine');
    expect(currentName($this->user))->toBe('Workout A');
});

test('archived workouts are skipped and the calendar wins for today', function () {
    routineOf($this->user, 'A', 'B', 'C');
    $this->user->forceFill(['next_routine_item_id' => RoutineItem::where('workout_id', $this->workouts['B']->id)->value('id')])->save();
    $this->workouts['B']->update(['archived_at' => now()]);

    expect(currentName($this->user))->toBe('Workout C');

    // Gepland in de kalender voor vandaag: dan geen schemakaart
    ScheduledWorkout::create(['user_id' => $this->user->id, 'workout_id' => $this->workouts['A']->id, 'scheduled_on' => today()]);
    Livewire::actingAs($this->user)->test(Dashboard::class)->assertDontSee('Daarna:')->assertSee('Workout A');

    // Zonder schema blijft het dashboard zoals het was
    RoutineItem::query()->delete();
    expect(Routine::current($this->user->fresh()))->toBeNull();
});

test('the whole schedule can be deleted, the workouts stay', function () {
    routineOf($this->user, 'A', 'B', 'C');
    Routine::advance($this->user);

    Livewire::actingAs($this->user)->test(RoutinePage::class)
        ->assertSee('Schema verwijderen')
        ->call('clear')
        ->assertSee('Nog geen schema');

    expect(RoutineItem::count())->toBe(0)
        ->and($this->user->fresh()->next_routine_item_id)->toBeNull()
        ->and(Workout::count())->toBe(3);
});
