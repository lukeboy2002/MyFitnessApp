<?php

use App\Actions\StartWorkoutSession;
use App\Livewire\Dashboard;
use App\Livewire\Profile\UpdateWeeklyGoal;
use App\Livewire\Progress\Exercise as ProgressExercise;
use App\Livewire\Progress\Index as ProgressIndex;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Support\ExerciseProgress;
use App\Support\TrainingStats;
use Carbon\CarbonImmutable;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 18:00')); // donderdag, week 42
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create(['weekly_goal_minutes' => 300]);

    $this->press = Exercise::factory()->create(['name' => 'Chest Press']);
    $this->press->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'chest')->value('id'));
    $this->run = Exercise::factory()->cardio()->create(['name' => 'Loopband']);
    $this->run->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'cardio')->value('id'));

    $this->workout = Workout::factory()->for($this->user)->create(['name' => 'Push']);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => $this->press->id, 'position' => 0, 'target_sets' => 2, 'target_reps' => 10, 'target_weight' => 40]);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => $this->run->id, 'position' => 1, 'target_sets' => null, 'target_reps' => null, 'rest_seconds' => null, 'target_duration_seconds' => 1200]);
});

/** Afgeronde training op $date van $minutes minuten, met sets [gewicht, reps] en cardio [meters, seconden] */
function trainingOn(string $date, int $minutes, array $sets, array $cardio = []): WorkoutSession
{
    test()->travelTo(CarbonImmutable::parse($date));
    $session = app(StartWorkoutSession::class)->handle(test()->user, test()->workout);
    [$press, $run] = $session->exercises()->get()->all();

    $press->sets()->delete();
    foreach ($sets as $i => [$weight, $reps]) {
        $press->sets()->create(['set_number' => $i + 1, 'weight' => $weight, 'reps' => $reps, 'is_completed' => true]);
    }
    if ($cardio) {
        $run->cardio->update(['distance_m' => $cardio[0], 'duration_seconds' => $cardio[1]]);
        $run->update(['completed_at' => now()]);
    }

    $session->finish();
    $session->update(['duration_seconds' => $minutes * 60]);
    test()->travelTo(CarbonImmutable::parse('2026-10-15 18:00'));

    return $session;
}

test('week totals count completed trainings from monday on', function () {
    trainingOn('2026-10-11 10:00', 60, [[40, 10]]);                 // zondag vorige week
    trainingOn('2026-10-12 10:00', 45, [[40, 10], [40, 10]]);       // maandag
    trainingOn('2026-10-14 10:00', 30, [[50, 5]], [5000, 1800]);    // woensdag
    app(StartWorkoutSession::class)->handle($this->user, $this->workout); // loopt nog, telt niet

    $stats = new TrainingStats($this->user);
    $week = $stats->totals(CarbonImmutable::now()->startOfWeek());

    expect($week)->toBe(['seconds' => 75 * 60, 'count' => 2, 'sets' => 3, 'volume' => 1050.0, 'distance_m' => 5000])
        ->and($stats->weeks(12))->toHaveCount(12)
        ->and($stats->weeks(12)->last())->toMatchArray(['seconds' => 75 * 60, 'count' => 2])
        ->and($stats->weeks(12)->get(10))->toMatchArray(['seconds' => 3600, 'count' => 1])
        ->and($stats->muscleGroups(CarbonImmutable::now()->startOfWeek())->pluck('count', 'name')->all())->toBe(['Chest' => 3, 'Cardio' => 1])
        ->and(TrainingStats::hours(75 * 60))->toBe('1u 15m');
});

test('the dashboard shows the week total and goal', function () {
    trainingOn('2026-10-12 10:00', 90, [[40, 10]]);

    Livewire::actingAs($this->user)->test(Dashboard::class)
        ->assertSee(['1u 30m', '/ 5u', 'Nog 3u 30m tot je weekdoel', 'Laatste 12 weken', 'Push']);

    $this->user->update(['weekly_goal_minutes' => null]);
    Livewire::actingAs($this->user)->test(Dashboard::class)->assertSee('Stel een weekdoel in');
});

test('the dashboard shows a running training', function () {
    $session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);

    Livewire::actingAs($this->user)->test(Dashboard::class)
        ->assertSee(['Training bezig', route('sessions.run', $session)]);
});

test('the weekly goal is saved in hours', function () {
    Livewire::actingAs($this->user)->test(UpdateWeeklyGoal::class)
        ->assertSet('hours', '5')
        ->set('hours', '4,5')->call('save')->assertHasNoErrors();
    expect($this->user->fresh()->weekly_goal_minutes)->toBe(270);

    Livewire::actingAs($this->user)->test(UpdateWeeklyGoal::class)->set('hours', '')->call('save');
    expect($this->user->fresh()->weekly_goal_minutes)->toBeNull();

    Livewire::actingAs($this->user)->test(UpdateWeeklyGoal::class)->set('hours', 'veel')->call('save')->assertHasErrors('hours');
});

test('the progress table shows every time with the difference', function () {
    trainingOn('2026-10-01 10:00', 60, [[40, 10], [45, 8]]);       // 1RM 57
    trainingOn('2026-10-08 10:00', 60, [[50, 5], [42.5, 10]]);     // 1RM 58,3
    trainingOn('2026-10-14 10:00', 60, [[40, 10]], [5000, 1800]);  // 1RM 53,3

    $progress = ExerciseProgress::for($this->user, $this->press);

    expect($progress->rows)->toHaveCount(3)
        ->and($progress->rows->pluck('one_rep_max')->all())->toBe([53.3, 58.3, 57.0])
        ->and($progress->rows->pluck('delta')->all())->toBe([-5.0, 1.3, null])
        ->and($progress->rows->first()['volume'])->toBe(400.0)
        ->and($progress->rows->get(1)['best'])->toBe('50 kg × 5')
        ->and($progress->points()->pluck('value')->all())->toBe([57.0, 58.3, 53.3]);

    Livewire::actingAs($this->user)->test(ProgressExercise::class, ['exercise' => $this->press])
        ->assertSee(['Records', 'Geschat 1RM', '58,3 kg', 'Alle keren', '▲ 1,3 kg', '▼ 5 kg']);

    Livewire::actingAs($this->user)->test(ProgressExercise::class, ['exercise' => $this->run])
        ->assertSee(['Afstand', '5 km', '30:00']);
});

test('the progress overview lists done exercises per period', function () {
    trainingOn('2026-09-20 10:00', 60, [[40, 10]]);
    trainingOn('2026-10-13 10:00', 50, [[40, 10]]);

    Livewire::actingAs($this->user)->test(ProgressIndex::class)
        ->assertSee(['50m', 'Chest Press', '2× gedaan'])
        ->assertDontSee('Loopband')
        ->set('period', 'year')->assertSee('1u 50m')
        ->set('search', 'press')->assertSee('Chest Press');
});

test('other users trainings are not shown', function () {
    trainingOn('2026-10-13 10:00', 50, [[40, 10]]);
    WorkoutSession::query()->update(['user_id' => User::factory()->create()->id]);

    expect(ExerciseProgress::for($this->user, $this->press)->rows)->toBeEmpty()
        ->and((new TrainingStats($this->user))->totals(CarbonImmutable::now()->startOfYear())['count'])->toBe(0);
});
