<?php

use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Livewire\Badges;
use App\Livewire\Dashboard;
use App\Livewire\Sessions\Summary;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\Achievements;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->press = Exercise::factory()->create(['name' => 'Chest Press']);
    $this->treadmill = Exercise::factory()->create(['name' => 'Loopband', 'type' => ExerciseType::Cardio]);
});

/** Afgeronde training met sets [reps, kg] van de chest press en eventueel cardio-meters */
function training(User $user, string $start, array $sets = [[10, 40]], int $minutes = 60, int $meters = 0, SessionStatus $status = SessionStatus::Completed): WorkoutSession
{
    $session = WorkoutSession::create([
        'user_id' => $user->id, 'name' => 'Push', 'color' => '#00468c', 'status' => $status,
        'started_at' => $start, 'ended_at' => CarbonImmutable::parse($start)->addMinutes($minutes), 'duration_seconds' => $minutes * 60,
    ]);

    $item = $session->exercises()->create(['exercise_id' => test()->press->id, 'position' => 1]);
    foreach ($sets as $i => [$reps, $weight]) {
        $item->sets()->create(['set_number' => $i + 1, 'reps' => $reps, 'weight' => $weight, 'is_completed' => true]);
    }

    if ($meters) {
        $session->exercises()->create(['exercise_id' => test()->treadmill->id, 'position' => 2])
            ->cardio()->create(['duration_seconds' => 1200, 'distance_m' => $meters]);
    }

    return $session;
}

test('badges and their levels come from the completed trainings', function () {
    $first = training($this->user, '2026-10-05 06:30', [[10, 40], [10, 40]], minutes: 95, meters: 3200); // maandag, vroeg
    $second = training($this->user, '2026-10-07 18:00', [[10, 45]]);                                     // record: zwaarder
    training($this->user, '2026-10-13 18:00');                                                             // week 2, 1e
    $fourth = training($this->user, '2026-10-17 21:30', [[12, 45]]);                                      // zaterdag, laat, record
    training($this->user, '2026-10-18 10:00', status: SessionStatus::Cancelled);                          // telt niet

    $badges = Achievements::for($this->user)->keyBy(fn ($progress) => $progress->badge->metric->value);

    expect($badges['sessions']->value)->toEqual(4)
        ->and($badges['sessions']->title())->toBe('Eerste training')
        ->and($badges['sessions']->earned[0]['session_id'])->toBe($first->id)
        ->and($badges['sessions']->next())->toBe(10)
        ->and($badges['sessions']->progress())->toBe(40)
        ->and($badges['records']->value)->toEqual(2)
        ->and($badges['records']->earned[0]['session_id'])->toBe($second->id)
        ->and($badges['streak']->value)->toEqual(2)
        ->and($badges['streak']->earned[0]['session_id'])->toBe($fourth->id)
        ->and($badges['early']->level())->toBe(1)
        ->and($badges['late']->level())->toBe(1)
        ->and($badges['long_sessions']->level())->toBe(1)
        ->and($badges['session_distance']->level())->toBe(1)  // 3,2 km in één training
        ->and($badges['volume']->value)->toEqual(800 + 450 + 400 + 540)
        ->and($badges['volume']->isEarned())->toBeFalse()
        ->and($badges['sessions']->tier())->toBe('brons');

    expect(Achievements::earnedIn($fourth)->map(fn ($row) => $row['badge']->labelFor($row['level']))->all())
        ->toBe(['2 weken op rij', 'Training na 21:00']);
});

test('the badges page shows earned and open badges', function () {
    training($this->user, '2026-10-05 18:00');

    Livewire::actingAs($this->user)->test(Badges::class)
        ->assertSee(['Laatst behaald', 'Eerste training', 'Behaald op', 'Volgende: 10 trainingen', '1 / 10 trainingen', 'Vroege vogel', 'Nog niet behaald']);

    $this->actingAs($this->user)->get(route('badges'))->assertOk();
});

test('new badges are shown after a training and on the dashboard', function () {
    $session = training($this->user, '2026-10-05 18:00');

    Livewire::actingAs($this->user)->test(Summary::class, ['session' => $session])
        ->assertSee(['Nieuwe badge!', 'Eerste training']);

    Livewire::actingAs($this->user)->test(Dashboard::class)
        ->assertSee(['Badges', 'Eerste training', 'Alle badges']);

    // Een volgende training zonder nieuwe badge
    $next = training($this->user, '2026-10-20 18:00');
    Livewire::actingAs($this->user)->test(Summary::class, ['session' => $next])->assertDontSee('Nieuwe badge');
});
