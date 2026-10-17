<?php

use App\Enums\SessionStatus;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Database\Seeders\BodyPartSeeder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Een mini-versie van de oude database, als SQLite-bestand */
function legacyDatabase(): string
{
    $path = storage_path('framework/testing/legacy.sqlite');
    @mkdir(dirname($path), 0777, true);
    @unlink($path);

    $db = new PDO('sqlite:'.$path);
    $db->exec(<<<'SQL'
        CREATE TABLE users (id, name, email);
        CREATE TABLE exercises (id, user_id, name, type);
        CREATE TABLE workouts (id, user_id, name, description, is_archived, updated_at, deleted_at);
        CREATE TABLE workout_exercises (id, workout_id, exercise_id, sort_order, duration_minutes, intensity, target_sets, reps_per_set, weight_kg, rest_seconds);
        CREATE TABLE workout_exercise_sets (id, workout_exercise_id, set_number, reps, weight_kg);
        CREATE TABLE workout_sessions (id, user_id, workout_id, status, started_at, ended_at, paused_duration, total_seconds, notes);
        CREATE TABLE session_exercise_logs (id, workout_session_id, exercise_id, sort_order, is_completed, duration_minutes, distance_km, intensity,
            incline_degrees, watts, mets, act_kcal, ttl_kcal, avg_heart_rate, stroke_rate, pace, interim, floors, rotations, avg_act_hr, device_notes, updated_at);
        CREATE TABLE session_set_logs (id, session_exercise_log_id, set_number, reps, weight_kg, rest_seconds, is_completed, completed_at);
        CREATE TABLE cardio_watch_logs (id, user_id, session_exercise_log_id, avg_heart_rate, active_kcal, total_kcal, steps, created_at, updated_at);

        INSERT INTO users VALUES (2, 'Antoine', 'antoine@la-online.nl');
        INSERT INTO exercises VALUES (6, NULL, 'Chest Press', 'strength'), (30, NULL, 'Treadmill', 'cardio'), (28, NULL, 'Stairmaster', 'cardio');
        INSERT INTO workouts VALUES (8, 2, 'Chest and Back', 'Cardio - Upper', 0, '2026-10-01 10:00:00', NULL),
                                    (3, 2, 'Oud schema', NULL, 1, '2026-05-01 10:00:00', NULL);
        INSERT INTO workout_exercises VALUES (1, 8, 30, 0, 22, 5.2, 1, NULL, NULL, 60), (2, 8, 6, 1, NULL, NULL, 4, 12, 18, 60);
        INSERT INTO workout_exercise_sets VALUES (1, 2, 1, 12, 23), (2, 2, 2, 12, 23);
        INSERT INTO workout_sessions VALUES (79, 2, 8, 'completed', '2026-10-16 09:41:47', '2026-10-16 11:03:30', 0, 4903, 'Goed gegaan'),
                                            (80, 2, NULL, 'completed', '2026-10-17 09:00:00', '2026-10-17 09:30:00', 0, 1800, NULL),
                                            (81, 2, 8, 'active', '2026-10-17 18:00:00', NULL, 0, 0, NULL);
        INSERT INTO session_exercise_logs VALUES
            (614, 79, 30, 1, 1, 22, 1.82, 5.2, 0, 94, 3.3, 161, 211, 117, NULL, '12:00', '11:27', NULL, NULL, NULL, NULL, '2026-10-16 10:17:51'),
            (615, 79, 6, 2, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Stoel 4', '2026-10-16 10:30:00'),
            (616, 79, 28, 3, 0, 5, NULL, 6, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-16 10:40:00'),
            (617, 80, 30, 1, 1, 30, 400, 5, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-17 09:30:00');
        INSERT INTO session_set_logs VALUES (1, 615, 1, 12, 27, 60, 1, '2026-10-16 10:20:00'), (2, 615, 2, 10, 27, 60, 1, '2026-10-16 10:22:00');
        INSERT INTO cardio_watch_logs VALUES (1, 2, 614, 118, 160, 50, 2400, '2026-10-16 10:20:00', '2026-10-16 10:20:00');
    SQL);

    return $path;
}

beforeEach(function () {
    $this->seed([BodyPartSeeder::class, ExerciseSeeder::class]);
    $this->user = User::factory()->create(['email' => 'antoine@la-online.nl']);
    $this->legacy = legacyDatabase();
});

test('workouts and trainings are copied from the old app', function () {
    $this->artisan('fitness:import-legacy', ['email' => 'antoine@la-online.nl', '--database' => $this->legacy])->assertSuccessful();

    $workouts = Workout::where('user_id', $this->user->id)->with('exercises.exercise')->get()->keyBy('name');
    expect($workouts)->toHaveCount(2)
        ->and($workouts['Oud schema']->isArchived())->toBeTrue()
        ->and($workouts['Chest and Back']->exercises->pluck('exercise.slug')->all())->toBe(['treadmill', 'chest-press-machine'])
        ->and($workouts['Chest and Back']->exercises[1]->only('target_sets', 'target_reps'))->toBe(['target_sets' => 2, 'target_reps' => 12])
        ->and((float) $workouts['Chest and Back']->exercises[1]->target_weight)->toBe(23.0);

    $sessions = WorkoutSession::where('user_id', $this->user->id)->orderBy('started_at')->get();
    expect($sessions)->toHaveCount(2) // de lopende training niet
        ->and($sessions[0]->only('name', 'duration_seconds', 'notes', 'external_id'))->toBe(['name' => 'Chest and Back', 'duration_seconds' => 4903, 'notes' => 'Goed gegaan', 'external_id' => 'myfitness:79'])
        ->and($sessions[0]->status)->toBe(SessionStatus::Completed)
        ->and($sessions[1]->name)->toBe(WorkoutSession::QUICKSTART_NAME);

    // Stairmaster zonder gegevens is niet gedaan en wordt overgeslagen
    $items = $sessions[0]->exercises()->with('exercise', 'sets', 'cardio.splits')->get();
    expect($items->pluck('exercise.slug')->all())->toBe(['treadmill', 'chest-press-machine']);

    $run = $items[0]->cardio;
    expect($run->only('duration_seconds', 'distance_m', 'avg_watts', 'active_kcal', 'total_kcal', 'avg_heart_rate', 'pace_seconds'))
        ->toBe(['duration_seconds' => 1320, 'distance_m' => 1820, 'avg_watts' => 94, 'active_kcal' => 161, 'total_kcal' => 211, 'avg_heart_rate' => 117, 'pace_seconds' => 720])
        ->and((float) $run->metric_value)->toBe(5.2)
        ->and($run->splits->pluck('duration_seconds')->all())->toBe([687])
        ->and($run->extra_metrics['watch'])->toMatchArray(['avg_heart_rate' => 118, 'steps' => 2400]);

    expect($items[1]->notes)->toBe('Stoel 4')
        ->and($items[1]->sets->map->only('reps', 'is_completed')->all())->toBe([['reps' => 12, 'is_completed' => true], ['reps' => 10, 'is_completed' => true]])
        ->and($items[1]->completed_at)->not->toBeNull();

    // Afstand die in meters is ingevuld
    expect($sessions[1]->exercises()->first()->cardio->distance_m)->toBe(400);
});

test('running the import twice does not copy anything twice', function () {
    $this->artisan('fitness:import-legacy', ['email' => 'antoine@la-online.nl', '--database' => $this->legacy])->assertSuccessful();
    $this->artisan('fitness:import-legacy', ['email' => 'antoine@la-online.nl', '--database' => $this->legacy])->assertSuccessful();

    expect(WorkoutSession::count())->toBe(2)->and(Workout::count())->toBe(2);
});

test('a dry run saves nothing', function () {
    $this->artisan('fitness:import-legacy', ['email' => 'antoine@la-online.nl', '--database' => $this->legacy, '--dry-run' => true])
        ->expectsOutputToContain('Proefrun')
        ->assertSuccessful();

    expect(WorkoutSession::count())->toBe(0)->and(Workout::count())->toBe(0)
        ->and(Exercise::whereNotNull('user_id')->count())->toBe(0);
});

test('an unknown email stops the import', function () {
    $this->artisan('fitness:import-legacy', ['email' => 'iemand@anders.nl', '--database' => $this->legacy])->assertFailed();
});
