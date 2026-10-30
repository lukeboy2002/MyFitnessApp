<?php

use App\Actions\StartWorkoutSession;
use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Livewire\Sessions\Run;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Support\ProgressionHint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Sets van de vorige keer: [[reps, kg], ...] */
function lastSets(array $sets): array
{
    return collect($sets)->mapWithKeys(fn (array $set, int $i) => [$i + 1 => (new SetLog)->forceFill([
        'set_number' => $i + 1, 'reps' => $set[0], 'weight' => $set[1], 'duration_seconds' => null, 'is_completed' => true,
    ])])->all();
}

test('below the top of the range: one more rep', function () {
    $hint = ProgressionHint::for(ExerciseType::Strength, lastSets([[12, 40], [12, 40], [12, 40]]), 12);

    expect($hint->text)->toBe('Vorige keer 3×12 met 40 kg. Probeer 13 herhalingen met 40 kg in elke set.')
        ->and($hint->reps)->toBe(13)
        ->and($hint->weight)->toBe(40.0);
});

test('mixed reps: the weakest set sets the goal', function () {
    $hint = ProgressionHint::for(ExerciseType::Strength, lastSets([[15, 40], [14, 40], [13, 40]]), 12);

    expect($hint->text)->toBe('Vorige keer 15, 14, 13 met 40 kg. Probeer 14 herhalingen met 40 kg in elke set.');
});

test('all sets at the top of the range: heavier and back to the target', function () {
    $hint = ProgressionHint::for(ExerciseType::Strength, lastSets([[15, 40], [15, 40], [15, 40]]), 12);

    expect($hint->text)->toBe('Vorige keer 3×15 met 40 kg gehaald. Probeer 45 kg en weer 12 herhalingen.')
        ->and($hint->reps)->toBe(12)
        ->and($hint->weight)->toBe(45.0);
});

test('lighter warm-up sets do not count', function () {
    $hint = ProgressionHint::for(ExerciseType::Strength, lastSets([[15, 20], [15, 45], [15, 45]]), 12);

    expect($hint->weight)->toBe(50.0)
        ->and($hint->text)->toStartWith('Vorige keer 2×15 met 45 kg gehaald.');
});

test('bodyweight: more reps without weight, less help when assisted', function () {
    expect(ProgressionHint::for(ExerciseType::Bodyweight, lastSets([[15, null], [15, 0]]), 12)->text)
        ->toBe('Vorige keer 2×15. Probeer 16 herhalingen in elke set.');

    $assisted = ProgressionHint::for(ExerciseType::Bodyweight, lastSets([[15, -20], [15, -20]]), 12);
    expect($assisted->weight)->toBe(-15.0)
        ->and($assisted->text)->toBe('Vorige keer 2×15 met -20 kg gehaald. Probeer -15 kg (minder hulp) en weer 12 herhalingen.');
});

test('no hint without history or for cardio and time', function () {
    expect(ProgressionHint::for(ExerciseType::Strength, [], 12))->toBeNull()
        ->and(ProgressionHint::for(ExerciseType::Cardio, lastSets([[15, 40]]), 12))->toBeNull()
        ->and(ProgressionHint::for(ExerciseType::Duration, lastSets([[15, 40]]), 12))->toBeNull();
});

test('the training screen shows the hint and can fill in the open sets', function () {
    $user = User::factory()->create();
    $press = Exercise::factory()->create(['name' => 'Chest Press']);
    $workout = Workout::factory()->for($user)->create();
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $press->id, 'target_sets' => 3, 'target_reps' => 12, 'target_weight' => 40]);

    // Vorige keer: 3 × 15 met 40 kg
    $previous = WorkoutSession::create([
        'user_id' => $user->id, 'workout_id' => $workout->id, 'name' => 'Push', 'color' => '#00468c', 'status' => SessionStatus::Completed,
        'started_at' => now()->subDays(3), 'ended_at' => now()->subDays(3)->addHour(), 'duration_seconds' => 3600,
    ]);
    $item = $previous->exercises()->create(['exercise_id' => $press->id, 'position' => 1]);
    foreach ([1, 2, 3] as $number) {
        $item->sets()->create(['set_number' => $number, 'reps' => 15, 'weight' => 40, 'is_completed' => true]);
    }

    $session = app(StartWorkoutSession::class)->handle($user, $workout);
    $today = $session->exercises()->first();
    $first = $today->sets()->first();
    $first->update(['is_completed' => true, 'reps' => 12, 'weight' => 40]);

    Livewire::actingAs($user)->test(Run::class, ['session' => $session])
        ->assertSee('Probeer 45 kg en weer 12 herhalingen.')
        ->assertSee('Overnemen')
        ->call('applyHint', $today->id)
        ->assertDontSee('Overnemen');

    // De afgevinkte set blijft zoals hij was, de open sets krijgen het voorstel
    expect($first->fresh()->weight)->toEqual(40)
        ->and($today->sets()->where('is_completed', false)->get()->map(fn ($set) => [(int) $set->reps, (float) $set->weight])->all())
        ->toBe([[12, 45.0], [12, 45.0]]);
});
