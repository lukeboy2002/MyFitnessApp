<?php

use App\Actions\StartWorkoutSession;
use App\Livewire\Sessions\Run;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Support\Achievements;
use App\Support\ExerciseProgress;
use App\Support\ExerciseRecords;
use App\Support\ProgressionHint;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create(['locale' => 'nl']);

    $this->press = Exercise::factory()->create(['name' => 'Chest Press']);
    $this->press->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'chest')->value('id'));

    $this->workout = Workout::factory()->for($this->user)->create(['name' => 'Push']);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => $this->press->id, 'position' => 0, 'target_sets' => 3, 'target_reps' => 10, 'target_weight' => 40]);
});

/** Afgeronde training met sets [gewicht, reps, opwarmset?] */
function trainingWithWarmup(int $daysAgo, array $sets): WorkoutSession
{
    test()->travelTo(now()->subDays($daysAgo));
    $session = app(StartWorkoutSession::class)->handle(test()->user, test()->workout);
    $press = $session->exercises()->sole();

    $press->sets()->delete();
    foreach ($sets as $i => [$weight, $reps, $warmup]) {
        $press->sets()->create(['set_number' => $i + 1, 'weight' => $weight, 'reps' => $reps, 'is_warmup' => $warmup, 'is_completed' => true]);
    }

    $session->finish();
    test()->travelBack();

    return $session;
}

test('a set can be marked as warm-up set and back during the training', function () {
    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    $set = $session->exercises()->sole()->sets()->first();

    $component = Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->call('toggleWarmup', $set->id)
        ->assertSee('Set 1 is een opwarmset');
    expect($set->fresh()->is_warmup)->toBeTrue();

    $component->call('toggleWarmup', $set->id);
    expect($set->fresh()->is_warmup)->toBeFalse();
});

test('warm-up sets do not count for records, 1RM, progress and the hint', function () {
    // Opwarmset met veel reps op 30 kg: zou anders "meeste reps" en een hoge 1RM geven
    trainingWithWarmup(3, [[30, 40, true], [40, 10, false], [40, 10, false]]);

    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    $records = ExerciseRecords::forSession($session)[$this->press->id];

    expect($records->heaviestWeight)->toBe(40.0)
        ->and($records->bestOneRepMax)->toBe(53.3)
        ->and($records->lastSets)->toHaveCount(2)
        ->and($records->lastTime)->toBe('2 × 10 · 40 kg');

    $hint = ProgressionHint::for($this->press->type, $records->lastSets, 10);
    expect($hint->reps)->toBe(11)->and($hint->weight)->toBe(40.0);

    $row = ExerciseProgress::for($this->user, $this->press)->rows->sole();
    expect($row['sets'])->toBe(2)->and($row['one_rep_max'])->toBe(53.3);

    // Vandaag: een zware opwarmset is geen record, een gewone set wel
    [$warmup, $normal] = $session->exercises()->sole()->sets->all();
    $warmup->update(['weight' => 60, 'reps' => 10, 'is_warmup' => true]);
    $normal->update(['weight' => 45, 'reps' => 10]);

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->call('toggleSet', $warmup->id)
        ->assertNotDispatched('record')
        ->call('toggleSet', $normal->id)
        ->assertDispatched('record');
});

test('applying the hint skips warm-up sets', function () {
    trainingWithWarmup(3, [[40, 10, false], [40, 10, false]]);

    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    $press = $session->exercises()->sole();
    $warmup = $press->sets()->first();
    $warmup->update(['weight' => 20, 'reps' => 15, 'is_warmup' => true]);

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])->call('applyHint', $press->id);

    expect($warmup->fresh())->reps->toBe(15)->weight->toBe('20.00')
        ->and($press->sets()->where('is_warmup', false)->pluck('reps')->unique()->all())->toBe([11]);
});

test('the heaviest weight badge ignores warm-up sets', function () {
    $session = trainingWithWarmup(1, [[100, 1, true], [40, 10, false]]);

    $perExercise = [];
    (new ReflectionMethod(Achievements::class, 'addPerExercise'))->invokeArgs(null, [&$perExercise, $session->exercises()->with('exercise', 'sets', 'cardio')->sole()]);

    expect($perExercise['exercise_weight'][$this->press->id])->toBe(40.0);
});

test('the migration adds and removes the column', function () {
    $migration = require database_path('migrations/2026_11_17_180000_add_is_warmup_to_set_logs_table.php');

    $migration->down();
    expect(Schema::hasColumn('set_logs', 'is_warmup'))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumn('set_logs', 'is_warmup'))->toBeTrue();
});
