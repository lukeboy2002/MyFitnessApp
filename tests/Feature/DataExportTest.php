<?php

use App\Enums\CardioMetric;
use App\Enums\SessionStatus;
use App\Models\CardioLog;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\DataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function exportCsv(string $type): array
{
    $content = test()->get(route('profile.export', $type))->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF");

    return array_map(fn (string $line) => str_getcsv($line, ';', escape: ''), explode("\n", trim(substr($content, 3))));
}

function completedSession(User $user, array $attributes = []): WorkoutSession
{
    return WorkoutSession::forceCreate([
        'user_id' => $user->id, 'name' => 'Workout A', 'status' => SessionStatus::Completed,
        'started_at' => '2026-11-02 18:30:00', 'ended_at' => '2026-11-02 19:30:00', 'duration_seconds' => 3600,
        ...$attributes,
    ]);
}

test('trainings: one row per set and one per cardio exercise', function () {
    $session = completedSession($this->user, ['notes' => 'Goed gegaan']);
    $press = Exercise::factory()->create(['name' => 'Chest Press']);
    $bike = Exercise::factory()->cardio()->create(['name' => 'Hometrainer']);

    $item = SessionExercise::forceCreate(['workout_session_id' => $session->id, 'exercise_id' => $press->id, 'position' => 1, 'completed_at' => now()]);
    SetLog::forceCreate(['session_exercise_id' => $item->id, 'set_number' => 1, 'reps' => 12, 'weight' => 42.5, 'is_completed' => true]);
    SetLog::forceCreate(['session_exercise_id' => $item->id, 'set_number' => 2, 'reps' => 10, 'weight' => 42.5, 'is_completed' => false]);

    $cardio = SessionExercise::forceCreate(['workout_session_id' => $session->id, 'exercise_id' => $bike->id, 'position' => 2, 'completed_at' => now()]);
    CardioLog::forceCreate(['session_exercise_id' => $cardio->id, 'duration_seconds' => 1230, 'distance_m' => 8450, 'metric' => CardioMetric::Level, 'metric_value' => 12, 'avg_heart_rate' => 128]);

    // Afgebroken trainingen tellen niet mee
    completedSession($this->user, ['status' => SessionStatus::Cancelled, 'name' => 'Afgebroken']);

    $rows = exportCsv('trainingen');
    $header = $rows[0];
    $row = fn (int $i) => array_combine($header, $rows[$i]);

    expect($rows)->toHaveCount(4)
        ->and($row(1))->toMatchArray(['Datum' => '02-11-2026', 'Starttijd' => '18:30', 'Training' => 'Workout A', 'Duur training (min)' => '60',
            'Oefening' => 'Chest Press', 'Set' => '1', 'Herhalingen' => '12', 'Gewicht (kg)' => '42,5', 'Afgerond' => 'ja', 'Notitie' => 'Goed gegaan'])
        ->and($row(2))->toMatchArray(['Set' => '2', 'Herhalingen' => '10', 'Afgerond' => 'nee'])
        ->and($row(3))->toMatchArray(['Oefening' => 'Hometrainer', 'Type' => 'Cardio', 'Afstand (km)' => '8,45', 'Cardiotijd' => '20:30',
            'Instelling' => 'Niveau', 'Waarde' => '12', 'Gem. hartslag' => '128', 'Set' => '']);
});

test('weights and measurements', function () {
    $this->user->bodyWeights()->create(['measured_on' => '2026-11-01', 'weight_kg' => 84.3, 'body_fat' => 21.5]);
    $this->user->bodyWeights()->create(['measured_on' => '2026-10-01', 'weight_kg' => 85]);
    $this->user->bodyMeasurements()->create(['measured_on' => '2026-11-01', 'waist_cm' => 92.5]);

    $weights = exportCsv('wegingen');
    expect($weights)->toBe([
        ['Datum', 'Gewicht (kg)', 'Vetpercentage (%)'],
        ['01-10-2026', '85', ''],
        ['01-11-2026', '84,3', '21,5'],
    ]);

    $measurements = exportCsv('maten');
    expect($measurements[0])->toContain('Taille (cm)')
        ->and(array_combine($measurements[0], $measurements[1]))->toMatchArray(['Datum' => '01-11-2026', 'Taille (cm)' => '92,5', 'Borst (cm)' => '']);
});

test('you only get your own data', function () {
    $other = User::factory()->create();
    $other->bodyWeights()->create(['measured_on' => '2026-11-01', 'weight_kg' => 70]);
    completedSession($other);

    expect(exportCsv('wegingen'))->toHaveCount(1)
        ->and(exportCsv('trainingen'))->toHaveCount(1);
});

test('the profile shows the downloads; unknown types and guests are refused', function () {
    $this->user->bodyWeights()->create(['measured_on' => '2026-11-01', 'weight_kg' => 84]);

    $this->get(route('profile.edit'))->assertOk()
        ->assertSee('Je gegevens downloaden')
        ->assertSee('1 weging')
        ->assertSee(route('profile.export', 'wegingen'))
        ->assertDontSee(route('profile.export', 'maten'));

    $this->get('/profile/export/wachtwoorden')->assertNotFound();

    auth()->logout();
    $this->get(route('profile.export', 'wegingen'))->assertRedirect(route('login'));
});

test('numbers and times are formatted for Dutch spreadsheets', function () {
    expect(DataExport::number(82.50, 2))->toBe('82,5')
        ->and(DataExport::number(80, 1))->toBe('80')
        ->and(DataExport::number(-12.5, 2))->toBe('-12,5')
        ->and(DataExport::number(null, 1))->toBe('')
        ->and(DataExport::duration(754))->toBe('12:34')
        ->and(DataExport::duration(3723))->toBe('1:02:03')
        ->and(DataExport::duration(null))->toBe('');
});
