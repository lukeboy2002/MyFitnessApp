<?php

use App\Actions\StartWorkoutSession;
use App\Livewire\Sessions\Run;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Support\ExerciseRecords;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create();

    $this->press = Exercise::factory()->create(['name' => 'Chest Press']);
    $this->press->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'chest')->value('id'));
    $this->bike = Exercise::factory()->cardio()->create(['name' => 'Loopband']);
    $this->bike->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'cardio')->value('id'));

    $this->workout = Workout::factory()->for($this->user)->create(['name' => 'Push']);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => $this->press->id, 'position' => 0, 'target_sets' => 2, 'target_reps' => 10, 'target_weight' => 40]);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => $this->bike->id, 'position' => 1, 'target_sets' => null, 'target_reps' => null, 'rest_seconds' => null, 'target_duration_seconds' => 1200]);
});

/** Een afgeronde training van $daysAgo dagen geleden met deze sets [gewicht, reps] en cardio [meters, seconden, snelste km] */
function pastTraining(int $daysAgo, array $sets, array $cardio = []): WorkoutSession
{
    test()->travelTo(now()->subDays($daysAgo));
    $session = app(StartWorkoutSession::class)->handle(test()->user, test()->workout);
    [$press, $bike] = $session->exercises()->with('sets')->get()->all();

    $press->sets()->delete();
    foreach ($sets as $i => [$weight, $reps]) {
        $press->sets()->create(['set_number' => $i + 1, 'weight' => $weight, 'reps' => $reps, 'is_completed' => true]);
    }
    if ($cardio) {
        $bike->cardio->update(['distance_m' => $cardio[0], 'duration_seconds' => $cardio[1], 'floors' => $cardio[3] ?? null]);
        $bike->cardio->splits()->create(['number' => 1, 'duration_seconds' => $cardio[2]]);
    }

    $session->finish();
    test()->travelBack();

    return $session;
}

test('records come from earlier completed trainings', function () {
    pastTraining(10, [[40, 10], [45, 8]], [5000, 1800, 330]);
    pastTraining(3, [[50, 5], [42.5, 10]], [6000, 2100, 340, 40]);

    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    $records = ExerciseRecords::forSession($session);

    expect($records[$this->press->id]->list())->toBe([
        'Zwaarste' => '50 kg × 5',
        'Geschat 1RM' => '58,3 kg',
        'Vorige keer' => '5, 10 reps · 42,5–50 kg',
    ])->and($records[$this->bike->id]->list())->toBe([
        'Langste afstand' => '6 km',
        'Langste tijd' => '35:00 min',
        'Snelste km' => '5:30',
        'Meeste floors' => 40,
        'Vorige keer' => '35:00 min · 6 km · 40 floors',
    ]);
});

test('a set that beats a record is marked', function () {
    pastTraining(3, [[50, 5]], [5000, 1800, 330]);

    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    [$press, $bike] = $session->exercises()->with('sets', 'cardio')->get()->all();
    $records = ExerciseRecords::forSession($session)[$this->press->id];

    $set = $press->sets->first();
    $set->update(['weight' => 45, 'reps' => 10, 'is_completed' => true]); // 1RM 60 > 58,3
    expect($records->isRecordSet($set))->toBeTrue();

    $set->update(['weight' => 40, 'reps' => 5]);
    expect($records->isRecordSet($set))->toBeFalse();

    $bike->cardio->update(['distance_m' => 5500, 'floors' => 12]);
    expect(ExerciseRecords::forSession($session)[$this->bike->id]->cardioRecords($bike->cardio->fresh()))->toBe(['Langste afstand']);

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->assertSee(['Zwaarste', '50 kg × 5', 'Vorige keer', 'Nieuw record: langste afstand']);
});

test('the first time there are no records', function () {
    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    $records = ExerciseRecords::forSession($session);

    expect($records[$this->press->id]->isEmpty())->toBeTrue();

    $set = $session->exercises()->first()->sets()->first();
    $set->update(['is_completed' => true]);
    expect($records[$this->press->id]->isRecordSet($set))->toBeFalse();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])->assertDontSee('Vorige keer')->assertSee('Eerste keer');
});

test('other users do not count for my records', function () {
    pastTraining(3, [[80, 5]]);
    WorkoutSession::query()->update(['user_id' => User::factory()->create()->id]);

    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    expect(ExerciseRecords::forSession($session)[$this->press->id]->isEmpty())->toBeTrue();
});

test('a new record is celebrated', function () {
    pastTraining(3, [[50, 5]], [5000, 1800, 330]);

    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    [$press, $bike] = $session->exercises()->with('sets', 'cardio')->get()->all();
    [$first, $second] = $press->sets->all();
    $first->update(['weight' => 52.5, 'reps' => 6]);
    $second->update(['weight' => 40, 'reps' => 5]);
    $bike->cardio->update(['distance_m' => 5500]);

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->call('toggleSet', $second->id)
        ->assertNotDispatched('record')
        ->call('toggleSet', $first->id)
        ->assertDispatched('record', title: 'Nieuw record!', text: 'Chest Press · 52,5 kg × 6')
        ->call('toggleCardio', $bike->id)
        ->assertDispatched('record', text: 'Loopband · langste afstand');
});

test('more floors is a cardio record', function () {
    pastTraining(3, [[50, 5]], [5000, 1800, 330, 40]);

    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    $bike = $session->exercises()->get()->last();
    $bike->cardio->update(['floors' => 45]);

    expect(ExerciseRecords::forSession($session)[$this->bike->id]->cardioRecords($bike->cardio->fresh()))->toBe(['Meeste floors']);
});

test('strength shows the previous training per set', function () {
    pastTraining(10, [[30, 12]]);
    pastTraining(3, [[41, 12], [46, 10]]);

    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    $records = ExerciseRecords::forSession($session)[$this->press->id];

    expect(array_map(fn ($set) => $records->describeSet($set), $records->lastSets))->toBe([1 => '41 kg × 12', 2 => '46 kg × 10']);

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])
        ->assertSeeInOrder(['Chest Press', 'Vorige', '41 kg × 12', '46 kg × 10', 'Loopband', 'Vorige keer'])
        ->assertDontSee('12, 10 reps');
});
