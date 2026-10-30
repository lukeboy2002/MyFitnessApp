<?php

use App\Enums\ExerciseType;
use App\Enums\FitnessGoal;
use App\Enums\SessionStatus;
use App\Models\Badge;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\Achievements;
use App\Support\BadgeProgress;
use App\Support\ExerciseRecords;
use App\Support\GoalProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Net als de database-cache online: waarden echt bewaren en uitpakken (zonder objecten)
    config(['cache.stores.array.serialize' => true]);

    $this->user = User::factory()->create(['goal' => FitnessGoal::Strength]);
    $this->press = Exercise::factory()->create(['name' => 'Chest Press']);
});

/** Afgeronde training met sets [reps, kg] van de chest press */
function pressTraining(User $user, string $start, array $sets = [[10, 40]], SessionStatus $status = SessionStatus::Completed): WorkoutSession
{
    $session = WorkoutSession::create([
        'user_id' => $user->id, 'name' => 'Push', 'color' => '#00468c', 'status' => $status,
        'started_at' => $start, 'ended_at' => now()->parse($start)->addHour(), 'duration_seconds' => 3600,
    ]);

    $item = $session->exercises()->create(['exercise_id' => test()->press->id, 'position' => 1]);
    foreach ($sets as $i => [$reps, $weight]) {
        $item->sets()->create(['set_number' => $i + 1, 'reps' => $reps, 'weight' => $weight, 'is_completed' => true]);
    }

    return $session;
}

/** SQL-queries die tijdens $callback de sets ophalen */
function setQueries(Closure $callback): int
{
    $count = 0;
    DB::listen(function ($query) use (&$count) {
        $count += (int) str_contains($query->sql, 'from "set_logs"');
    });
    $callback();

    return $count;
}

test('badges come from the cache until a training is added', function () {
    pressTraining($this->user, now()->subDays(3)->toDateTimeString());
    $first = Achievements::for($this->user);

    // Tweede keer: dezelfde uitkomst, zonder alle sets opnieuw op te halen
    expect(setQueries(fn () => $second = Achievements::for($this->user)))->toBe(0);
    $second = Achievements::for($this->user);
    expect($second->map(fn (BadgeProgress $badge) => $badge->state())->all())->toBe($first->map(fn (BadgeProgress $badge) => $badge->state())->all())
        ->and($second->first()->earnedAt())->toBeInstanceOf(DateTimeInterface::class);

    // Een nieuwe afgeronde training: opnieuw rekenen
    pressTraining($this->user, now()->subDay()->toDateTimeString(), [[10, 50]]);
    $sessions = Achievements::for($this->user)->first(fn (BadgeProgress $badge) => $badge->badge->metric->value === 'sessions');
    expect($sessions->value)->toEqual(2);
});

test('a running training does not clear the cache, finishing it does', function () {
    pressTraining($this->user, now()->subDays(3)->toDateTimeString());
    Achievements::for($this->user);

    $running = pressTraining($this->user, now()->subMinutes(10)->toDateTimeString(), status: SessionStatus::Active);
    expect(setQueries(fn () => Achievements::for($this->user)))->toBe(0);

    $running->finish();
    expect(setQueries(fn () => Achievements::for($this->user)))->toBeGreaterThan(0);
});

test('a changed badge threshold is picked up right away', function () {
    pressTraining($this->user, now()->subDays(3)->toDateTimeString());
    Achievements::for($this->user);

    $badge = Badge::whereJsonLength('thresholds', '>', 0)->get()->first(fn (Badge $badge) => $badge->metric->value === 'sessions');
    $badge->update(['thresholds' => [5, 10]]);

    $sessions = Achievements::for($this->user)->get($badge->id);
    expect($sessions->isEarned())->toBeFalse()
        ->and($sessions->next())->toBe(5);
});

test('records on the training screen are calculated once per training', function () {
    pressTraining($this->user, now()->subDays(3)->toDateTimeString(), [[10, 40], [8, 45]]);
    $running = pressTraining($this->user, now()->subMinutes(5)->toDateTimeString(), [[10, 40]], SessionStatus::Active);

    $first = ExerciseRecords::forSession($running)[$this->press->id];

    expect(setQueries(fn () => ExerciseRecords::forSession($running->fresh())))->toBe(0);
    $cached = ExerciseRecords::forSession($running->fresh())[$this->press->id];

    expect($cached->heaviestWeight)->toEqual(45.0)
        ->and($cached->list())->toBe($first->list())
        ->and($cached->lastSets)->toHaveCount(2)
        ->and($cached->describeSet($cached->lastSets[2]))->toBe('45 kg × 8')
        ->and($cached->type)->toBe(ExerciseType::Strength);

    // Oefening erbij tijdens de training: opnieuw rekenen, met de nieuwe oefening erin
    $bike = Exercise::factory()->create(['type' => ExerciseType::Cardio]);
    $running->exercises()->create(['exercise_id' => $bike->id, 'position' => 2]);
    expect(ExerciseRecords::forSession($running->fresh()))->toHaveKeys([$this->press->id, $bike->id]);
});

test('the strength goal counts records from the cache', function () {
    pressTraining($this->user, now()->subDays(20)->toDateTimeString(), [[10, 40]]);
    pressTraining($this->user, now()->subDays(10)->toDateTimeString(), [[10, 45]]);   // record

    expect(GoalProgress::for($this->user)->value)->toBe('1');
    expect(setQueries(fn () => GoalProgress::for($this->user)))->toBe(0);

    pressTraining($this->user, now()->subDays(2)->toDateTimeString(), [[10, 50]]);    // nog een record
    expect(GoalProgress::for($this->user)->value)->toBe('2');
});
