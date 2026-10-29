<?php

use App\Actions\StartWorkoutSession;
use App\Enums\SessionStatus;
use App\Livewire\Exercises\Index as ExercisesIndex;
use App\Livewire\Sessions\Run;
use App\Livewire\Sessions\Summary;
use App\Livewire\Workouts\Show;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create();

    $chest = MuscleGroup::whereNull('user_id')->where('slug', 'chest')->value('id');
    $cardio = MuscleGroup::whereNull('user_id')->where('slug', 'cardio')->value('id');

    $this->press = Exercise::factory()->create(['name' => 'Chest Press']);
    $this->press->syncMuscleGroups($chest);
    $this->bike = Exercise::factory()->cardio()->create(['name' => 'Hometrainer', 'default_metric' => 'resistance']);
    $this->bike->syncMuscleGroups($cardio);

    $this->workout = Workout::factory()->for($this->user)->create(['name' => 'Push']);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => $this->press->id, 'position' => 0, 'target_sets' => 3, 'target_reps' => 10, 'target_weight' => 40, 'rest_seconds' => 60]);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => $this->bike->id, 'position' => 1, 'target_sets' => null, 'target_reps' => null, 'rest_seconds' => null, 'target_duration_seconds' => 1200, 'target_metric' => 'resistance', 'target_metric_value' => 8]);
});

function startSession(): WorkoutSession
{
    return app(StartWorkoutSession::class)->handle(test()->user, test()->workout);
}

test('starting a workout copies the exercises, targets and sets', function () {
    Livewire::actingAs($this->user)->test(Show::class, ['workout' => $this->workout])
        ->call('start')
        ->assertRedirect(route('sessions.run', WorkoutSession::first()));

    $session = WorkoutSession::first();
    [$press, $bike] = $session->exercises()->with('sets', 'cardio')->get()->all();

    expect($session->name)->toBe('Push')
        ->and($session->status)->toBe(SessionStatus::Active)
        ->and($press->sets)->toHaveCount(3)
        ->and($press->sets->first()->reps)->toBe(10)
        ->and($press->sets->first()->weight)->toBe('40.00')
        ->and($bike->cardio->duration_seconds)->toBe(1200)
        ->and($bike->cardio->metric_value)->toBe('8.0');

    // Wijzigen van de workout verandert de training niet
    $this->workout->exercises()->first()->update(['target_reps' => 12]);
    expect($press->fresh()->target_reps)->toBe(10);
});

test('only one training can run at a time', function () {
    $first = startSession();
    $other = Workout::factory()->for($this->user)->create();
    WorkoutExercise::factory()->for($other)->create(['exercise_id' => $this->press->id]);

    expect(app(StartWorkoutSession::class)->handle($this->user, $other)->id)->toBe($first->id)
        ->and(WorkoutSession::count())->toBe(1);
});

test('sets can be edited, ticked off and added', function () {
    $session = startSession();
    $press = $session->exercises()->first();
    $set = $press->sets()->first();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->set("values.{$set->id}.weight", '42,5')
        ->set("values.{$set->id}.reps", 8)
        ->call('toggleSet', $set->id)
        ->assertDispatched('rest-start', seconds: 60)
        ->call('addSet', $press->id)
        ->call('removeSet', $press->id)
        ->call('addSet', $press->id);

    $set->refresh();
    expect($set->weight)->toBe('42.50')
        ->and($set->reps)->toBe(8)
        ->and($set->is_completed)->toBeTrue()
        ->and($press->sets()->count())->toBe(4)
        ->and($press->sets()->max('set_number'))->toBe(4);
});

test('cardio data can be filled in', function () {
    $session = startSession();
    $bike = $session->exercises()->get()->last();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->assertSet("cardio.{$bike->id}.time", '20:00')
        ->set("cardio.{$bike->id}.time", '21:30')
        ->set("cardio.{$bike->id}.distance", '8,4')
        ->set("cardio.{$bike->id}.metric_value", '9')
        ->set("cardio.{$bike->id}.avg_watts", '145')
        ->set("cardio.{$bike->id}.mets", '7,5')
        ->set("cardio.{$bike->id}.active_kcal", 'veel')
        ->assertHasErrors("cardio.{$bike->id}.active_kcal")
        ->call('toggleCardio', $bike->id);

    $log = $bike->cardio()->first();
    expect($log->duration_seconds)->toBe(1290)
        ->and($log->distance_m)->toBe(8400)
        ->and($log->metric_value)->toBe('9.0')
        ->and($log->avg_watts)->toBe(145)
        ->and($log->mets)->toBe('7.5')
        ->and($log->active_kcal)->toBeNull()
        ->and($bike->fresh()->completed_at)->not->toBeNull();
});

test('the extra fields of a cardio machine can be filled in', function () {
    $this->bike->update(['cardio_fields' => ['rotations', 'floors', 'stroke_rate', 'pace']]);
    $session = startSession();
    $bike = $session->exercises()->get()->last();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->assertSee(['Rotaties', 'Floors', 'Stroke rate', 'Pace', 'AVG ACT/HR'])
        ->assertDontSee(['Tussentijden per km', 'Helling', 'Cadans'])
        ->set("cardio.{$bike->id}.rotations", '1250')
        ->set("cardio.{$bike->id}.floors", '42')
        ->set("cardio.{$bike->id}.stroke_rate", '28')
        ->set("cardio.{$bike->id}.pace", '2:05')
        ->set("cardio.{$bike->id}.avg_act_hr", '480')
        ->set("cardio.{$bike->id}.floors", '4,5')
        ->assertHasErrors("cardio.{$bike->id}.floors");

    $log = $bike->cardio()->first();
    expect($log->only('rotations', 'floors', 'stroke_rate', 'pace_seconds', 'avg_act_hr'))
        ->toBe(['rotations' => 1250, 'floors' => 42, 'stroke_rate' => 28, 'pace_seconds' => 125, 'avg_act_hr' => 480]);

    $session->finish();
    $this->actingAs($this->user)->get(route('sessions.summary', $session))->assertSee(['pace 2:05', '28 spm', '1250 rotaties', '42 floors']);
});

test('split times per km can be filled in', function () {
    $this->bike->update(['cardio_fields' => ['splits']]);
    $session = startSession();
    $bike = $session->exercises()->get()->last();

    $component = Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->call('addSplit', $bike->id)
        ->assertDispatched('split-added', id: $bike->id)
        ->set("splits.{$bike->id}.1", '11:20')
        ->call('addSplit', $bike->id)
        ->set("splits.{$bike->id}.2", 'snel')
        ->assertHasErrors("splits.{$bike->id}.2")
        ->set("splits.{$bike->id}.2", '11.05')
        ->assertHasNoErrors()
        ->call('addSplit', $bike->id)
        ->set("splits.{$bike->id}.3", '10:50')
        ->call('addSplit', $bike->id)
        ->set("splits.{$bike->id}.4", '11:00')
        ->call('addSplit', $bike->id)
        ->set("splits.{$bike->id}.5", '10:40')
        ->assertSee('5,4 km/h')
        ->assertSee('Volgende km');

    $log = $bike->cardio()->first();
    expect($log->kmTimes())->toBe([1 => 680, 2 => 665, 3 => 650, 4 => 660, 5 => 640]);

    // Km 2 weg: de rest schuift op
    $component->call('removeSplit', $bike->id, 2)
        ->assertSet("splits.{$bike->id}", [1 => '11:20', 2 => '10:50', 3 => '11:00', 4 => '10:40']);
    expect($log->refresh()->kmTimes())->toBe([1 => 680, 2 => 650, 3 => 660, 4 => 640]);

    // De browser kan de hele lijst in één keer sturen
    $component->set("splits.{$bike->id}", [null, '11:20', '10:40']);
    expect($log->refresh()->kmTimes())->toBe([1 => 680, 2 => 640, 3 => 660, 4 => 640]);

    $session->finish();
    $this->actingAs($this->user)->get(route('sessions.summary', $session))->assertSee('km 2 · 10:40');
});

test('the timer leaves out pauses and is stored when finishing', function () {
    $this->travelTo(now()->startOfMinute());
    $session = startSession();

    $this->travel(10)->minutes();
    $component = Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])->call('pause');
    $this->travel(5)->minutes();
    $component->call('resume');
    $this->travel(2)->minutes();

    expect($session->fresh()->elapsedSeconds())->toBe(12 * 60);

    $set = $session->exercises()->first()->sets()->first();
    $component->call('toggleSet', $set->id)->call('finish')->assertRedirect(route('sessions.summary', $session));

    $session->refresh();
    expect($session->status)->toBe(SessionStatus::Completed)
        ->and($session->duration_seconds)->toBe(12 * 60)
        ->and($session->volume())->toBe(400.0);

    Livewire::actingAs($this->user)->test(Summary::class, ['session' => $session])
        ->assertSee('12:00')->assertSee('400 kg')->assertSee('Chest Press');
});

test('a training can be stopped without saving', function () {
    $session = startSession();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->call('cancel')
        ->assertRedirect(route('workouts.show', $this->workout));

    expect(WorkoutSession::count())->toBe(0);
});

test('the order can be changed during a training', function () {
    $session = startSession();
    $ids = $session->exercises()->pluck('id');
    $order = fn () => $session->exercises()->pluck('id')->all();

    $component = Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->call('moveToEnd', $ids[0]);
    expect($order())->toBe([...$ids->slice(1)->values(), $ids[0]]);

    $component->call('sortExercise', $ids[0], 0);
    expect($order())->toBe($ids->all());

    $other = WorkoutSession::create(['user_id' => $this->user->id, 'name' => 'Los', 'started_at' => now()->subDay(), 'status' => 'completed']);
    $foreign = $other->exercises()->create(['exercise_id' => Exercise::value('id')]);
    $component->call('sortExercise', $foreign->id, 0)->assertNotFound();
    expect($order())->toBe($ids->all());
});

test('other users cannot open my training', function () {
    $session = startSession();

    $this->actingAs(User::factory()->create())->get(route('sessions.run', $session))->assertForbidden();
});

test('the training pages render', function () {
    $session = startSession();

    $this->actingAs($this->user)->get(route('sessions.run', $session))->assertOk()->assertSee('Chest Press')->assertSee('Afronden');
    $this->actingAs($this->user)->get(route('workouts.show', $this->workout))->assertSee('Verder met training');

    $session->finish();
    $this->actingAs($this->user)->get(route('sessions.run', $session))->assertRedirect(route('sessions.summary', $session));
    $this->actingAs($this->user)->get(route('sessions.summary', $session))->assertOk();
});

test('an exercise with history cannot be deleted', function () {
    $mine = Exercise::factory()->create(['user_id' => $this->user->id]);
    $mine->syncMuscleGroups(MuscleGroup::whereNull('user_id')->value('id'));
    $session = WorkoutSession::create(['user_id' => $this->user->id, 'name' => 'Los', 'started_at' => now()]);
    $session->exercises()->create(['exercise_id' => $mine->id]);

    Livewire::actingAs($this->user)->test(ExercisesIndex::class)
        ->call('delete', $mine->id)
        ->assertSet('error', fn ($error) => str_contains($error, 'historie'));

    expect($mine->fresh())->not->toBeNull();
});

test('an exercise can be removed during a training, the workout stays the same', function () {
    $session = startSession();
    [$press, $bike] = $session->exercises->all();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->call('confirmDelete', $press->id)->assertSet('deletingName', 'Chest Press')
        ->call('deleteConfirmed')
        ->assertSet('confirmingDeletion', false);

    expect($session->exercises()->pluck('id')->all())->toBe([$bike->id])
        ->and($bike->fresh()->position)->toBe(1)
        ->and(SetLog::count())->toBe(0)
        ->and($this->workout->exercises()->count())->toBe(2);
});

test('cardio shows the average speed', function () {
    $session = startSession();
    $bike = $session->exercises->last();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->set("cardio.{$bike->id}.time", '30')
        ->set("cardio.{$bike->id}.distance", '12,2')
        ->assertSee('24,4 km/h');
});

test('an old tab cannot change a training that was finished elsewhere', function () {
    $session = startSession();
    $item = $session->exercises()->first();
    $set = $item->sets()->first();
    $setCount = $item->sets()->count();

    $component = Livewire::actingAs($this->user)->test(Run::class, ['session' => $session]);

    // Afgerond op een ander apparaat
    $session->finish();

    $component->call('toggleSet', $set->id)->assertRedirect(route('sessions.summary', $session));
    $component->set("values.{$set->id}.reps", '99');
    $component->call('addSet', $item->id);
    $component->call('delete', $item->id);
    $component->call('cancel');

    expect($set->fresh()->is_completed)->toBeFalse();
    expect($set->fresh()->reps)->not->toBe(99);
    expect($item->sets()->count())->toBe($setCount);
    expect($item->fresh())->not->toBeNull();
    expect($session->fresh())->not->toBeNull();
    expect($session->fresh()->status)->toBe(SessionStatus::Completed);
});

test('returning to an old tab sends you to the summary', function () {
    $session = startSession();
    $component = Livewire::actingAs($this->user)->test(Run::class, ['session' => $session]);

    $component->call('checkRunning')->assertNoRedirect();

    $session->finish();
    $component->call('checkRunning')->assertRedirect(route('sessions.summary', $session));
});
