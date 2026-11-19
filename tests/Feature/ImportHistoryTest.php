<?php

use App\Actions\ImportWorkoutHistory;
use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Livewire\Profile\ImportHistory;
use App\Models\DataImport;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\Import\ExerciseMatcher;
use App\Support\Import\WorkoutFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const HEVY_CSV = <<<'CSV'
"title","start_time","end_time","description","exercise_title","superset_id","exercise_notes","set_index","set_type","weight_kg","reps","distance_km","duration_seconds","rpe"
"Push dag","26 Jun 2024, 07:30","26 Jun 2024, 08:25","Goed gegaan","Bench Press (Barbell)",,"",0,"warmup",40,10,,,
"Push dag","26 Jun 2024, 07:30","26 Jun 2024, 08:25","Goed gegaan","Bench Press (Barbell)",,"",1,"normal",80,8,,,8
"Push dag","26 Jun 2024, 07:30","26 Jun 2024, 08:25","Goed gegaan","Bench Press (Barbell)",,"",2,"normal",80,7,,,9
"Push dag","26 Jun 2024, 07:30","26 Jun 2024, 08:25","Goed gegaan","Sled Push",,"",0,"normal",100,,0.02,,
"Push dag","26 Jun 2024, 07:30","26 Jun 2024, 08:25","Goed gegaan","Treadmill",,"",0,"normal",,,2.5,900,
"Benen","28 Jun 2024, 18:00","28 Jun 2024, 19:00","","Squat (Barbell)",,"",0,"normal",100,5,,,
CSV;

const STRONG_CSV = <<<'CSV'
Date;Workout Name;Duration;Exercise Name;Set Order;Weight;Reps;Distance;Seconds;Notes;Workout Notes;RPE
2024-01-15 18:30:00;Upper A;1h 2m;Bench Press (Barbell);W;40;10;;;;;
2024-01-15 18:30:00;Upper A;1h 2m;Bench Press (Barbell);1;82,5;8;;;Paused;;8
2024-01-15 18:30:00;Upper A;1h 2m;Bench Press (Barbell);Rest Timer;;;;90;;;
2024-01-15 18:30:00;Upper A;1h 2m;Plank;1;;;;60;;;
CSV;

const FITNOTES_CSV = <<<'CSV'
Date,Exercise,Category,Weight (kgs),Reps,Distance,Distance Unit,Time,Comment
2024-03-01,Flat Barbell Bench Press,Chest,60.0,10,,,,
2024-03-01,Barbell Curl,Biceps,30.0,12,,,,
2024-03-01,Treadmill,Cardio,,,3.0,km,0:20:00,
CSV;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['locale' => 'nl']);
    $this->bench = Exercise::factory()->create(['user_id' => null, 'name' => 'Bench Press (Barbell)', 'slug' => 'bench-press-barbell', 'type' => ExerciseType::Strength]);
    $this->squat = Exercise::factory()->create(['user_id' => null, 'name' => 'Squat (Barbell)', 'slug' => 'squat-barbell', 'type' => ExerciseType::Strength]);
    $this->treadmill = Exercise::factory()->create(['user_id' => null, 'name' => 'Treadmill', 'slug' => 'treadmill', 'type' => ExerciseType::Cardio]);
    $this->plank = Exercise::factory()->create(['user_id' => null, 'name' => 'Plank', 'slug' => 'plank', 'type' => ExerciseType::Duration]);
    $this->curl = Exercise::factory()->create(['user_id' => null, 'name' => 'Bicep Curl (Barbell)', 'slug' => 'bicep-curl-barbell', 'type' => ExerciseType::Strength]);
});

function csvFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $content);

    return $path;
}

test('it recognises Hevy, Strong and FitNotes exports and reads sets, units and durations', function () {
    $hevy = WorkoutFile::read(csvFile(HEVY_CSV));
    expect($hevy->app)->toBe('hevy')->and($hevy->needsUnit())->toBeFalse();
    $sessions = $hevy->sessions();
    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]['name'])->toBe('Push dag')
        ->and($sessions[0]['duration'])->toBe(55 * 60)
        ->and($sessions[0]['exercises'][0]['sets'][0]['warmup'])->toBeTrue()
        ->and($sessions[0]['exercises'][2]['sets'][0])->toMatchArray(['seconds' => 900, 'distance_m' => 2500]);

    $strong = WorkoutFile::read(csvFile(STRONG_CSV));
    expect($strong->app)->toBe('strong')->and($strong->needsUnit())->toBeTrue();
    $session = $strong->sessions()[0];
    expect($session['duration'])->toBe(62 * 60)
        ->and($session['exercises'][0]['sets'])->toHaveCount(2) // rusttimer telt niet
        ->and($session['exercises'][0]['sets'][1]['weight'])->toBe(82.5)
        ->and($strong->sessions(imperial: true)[0]['exercises'][0]['sets'][1]['weight'])->toBe(37.42);

    $fitnotes = WorkoutFile::read(csvFile(FITNOTES_CSV));
    expect($fitnotes->app)->toBe('fitnotes')
        ->and($fitnotes->sessions()[0]['name'])->toBe('Chest + Biceps + Cardio')
        ->and($fitnotes->sessions()[0]['exercises'][2]['sets'][0])->toMatchArray(['seconds' => 1200, 'distance_m' => 3000]);

    expect(fn () => WorkoutFile::read(csvFile("naam,leeftijd\nPiet,40")))->toThrow(RuntimeException::class);
});

test('exercise names are linked automatically or suggested', function () {
    $matcher = new ExerciseMatcher($this->user);

    expect($matcher->match('Bench Press (Barbell)'))->toBe(['id' => $this->bench->id, 'sure' => true])
        ->and($matcher->match('barbell squat'))->toBe(['id' => $this->squat->id, 'sure' => true])
        ->and($matcher->match('Barbell Curl'))->toBe(['id' => $this->curl->id, 'sure' => false])
        ->and($matcher->match('Sled Push'))->toBe(['id' => null, 'sure' => false]);
});

test('importing creates finished private trainings once, and undo removes them', function () {
    $file = WorkoutFile::read(csvFile(HEVY_CSV));
    $map = ['Bench Press (Barbell)' => $this->bench->id, 'Sled Push' => 0, 'Treadmill' => $this->treadmill->id, 'Squat (Barbell)' => $this->squat->id];

    $import = app(ImportWorkoutHistory::class)->run($this->user, $file, $map, 'workouts.csv');

    expect($import->sessions_count)->toBe(2)->and($import->skipped_count)->toBe(0);
    $session = WorkoutSession::where('user_id', $this->user->id)->where('name', 'Push dag')->with('exercises.sets', 'exercises.cardio')->first();
    expect($session->status)->toBe(SessionStatus::Completed)
        ->and($session->source)->toBe('hevy')
        ->and($session->isShared())->toBeFalse()
        ->and($session->exercises)->toHaveCount(2)
        ->and($session->exercises[0]->sets)->toHaveCount(3)
        ->and($session->exercises[0]->sets[0]->is_warmup)->toBeTrue()
        ->and((float) $session->exercises[0]->sets[1]->weight)->toBe(80.0)
        ->and($session->exercises[1]->cardio->distance_m)->toBe(2500)
        ->and($session->volume())->toBe(80.0 * 8 + 80 * 7 + 40 * 10);

    // Nog een keer: niets dubbel
    $again = app(ImportWorkoutHistory::class)->run($this->user, $file, $map, 'workouts.csv');
    expect($again->sessions_count)->toBe(0)->and($again->skipped_count)->toBe(2)
        ->and(WorkoutSession::where('user_id', $this->user->id)->count())->toBe(2);

    app(ImportWorkoutHistory::class)->undo($import);
    expect(WorkoutSession::where('user_id', $this->user->id)->count())->toBe(0)
        ->and(DataImport::find($import->id))->toBeNull();
});

test('the profile card walks through upload, linking and import', function () {
    $component = Livewire::actingAs($this->user)->test(ImportHistory::class)
        ->set('file', UploadedFile::fake()->createWithContent('workouts.csv', HEVY_CSV))
        ->assertHasNoErrors()
        ->assertSee(['Bestand van Hevy', '2 trainingen importeren', 'Sled Push']);

    expect($component->get('map'))->toMatchArray(['Bench Press (Barbell)' => $this->bench->id, 'Sled Push' => null])
        ->and(Storage::disk('local')->files('imports'))->toHaveCount(1);

    $component->call('createOwn', 'Sled Push');
    $own = Exercise::where('user_id', $this->user->id)->where('name', 'Sled Push')->first();
    expect($own->type)->toBe(ExerciseType::Cardio)
        ->and($component->get('map')['Sled Push'])->toBe($own->id);

    $component->call('choose', 'Squat (Barbell)')->assertSet('showPicker', true)
        ->call('pick', $this->squat->id)->assertSet('showPicker', false);

    $component->call('import')
        ->assertSet('stored', null)
        ->assertSee(['Import klaar', '2 trainingen toegevoegd aan je geschiedenis.', 'Eerdere imports']);
    expect(Storage::disk('local')->files('imports'))->toBeEmpty()
        ->and(WorkoutSession::where('user_id', $this->user->id)->count())->toBe(2);

    $import = DataImport::where('user_id', $this->user->id)->first();
    $component->call('confirmUndo', $import->id)->call('undo');
    expect(WorkoutSession::where('user_id', $this->user->id)->count())->toBe(0);
});

test('an unknown file is refused and you cannot undo someone else\'s import', function () {
    Livewire::actingAs($this->user)->test(ImportHistory::class)
        ->set('file', UploadedFile::fake()->createWithContent('lijst.csv', "naam,leeftijd\nPiet,40"))
        ->assertHasErrors('file')
        ->assertSet('stored', null);

    $other = DataImport::create(['user_id' => User::factory()->create()->id, 'app' => 'hevy', 'filename' => 'x.csv']);
    Livewire::actingAs($this->user)->test(ImportHistory::class)
        ->call('confirmUndo', $other->id)
        ->assertNotFound();
});
