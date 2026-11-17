<?php

use App\Actions\StartWorkoutSession;
use App\Livewire\Sessions\Run;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create(['locale' => 'nl']);

    $this->press = Exercise::factory()->create(['name' => 'Chest Press']);
    $this->press->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'chest')->value('id'));
    $this->bike = Exercise::factory()->cardio()->create(['name' => 'Hometrainer']);
    $this->bike->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'cardio')->value('id'));

    $this->workout = Workout::factory()->for($this->user)->create(['name' => 'Push']);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => $this->press->id, 'position' => 0, 'target_sets' => 3, 'target_reps' => 10, 'target_weight' => 40]);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => $this->bike->id, 'position' => 1, 'target_duration_seconds' => 1200]);

    $this->session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    [$this->pressItem, $this->bikeItem] = $this->session->exercises()->get()->all();
    $this->sets = $this->pressItem->sets()->orderBy('set_number')->get();
});

test('the training screen loads the offline layer', function () {
    $this->actingAs($this->user)->get(route('sessions.run', $this->session))
        ->assertOk()
        ->assertSee('offlineTraining('.$this->session->id.')', false)
        ->assertSee('data-rendered', false)
        ->assertSee('data-done="0"', false)
        ->assertSee('Geen bereik');
});

test('what was done without a connection is saved in one go', function () {
    $this->travel(10)->minutes();
    $at = now()->subMinutes(3);

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $this->session])
        ->call('syncOffline', [
            'sets' => [
                $this->sets[0]->id => ['weight' => '42,5', 'reps' => '12', 'done' => true, 'at' => $at->getTimestampMs()],
                $this->sets[1]->id => ['warmup' => true, 'done' => false],
            ],
            'cardio' => [
                $this->bikeItem->id => ['fields' => ['time' => '21:30', 'distance' => '8,4'], 'done' => true, 'at' => $at->getTimestampMs()],
            ],
            'splits' => [
                $this->bikeItem->id => [1 => '5:10', 2 => '5:05'],
            ],
        ])
        ->assertHasNoErrors()
        ->assertDispatched('offline-synced')
        ->assertSet("values.{$this->sets[0]->id}.weight", '42,5')
        ->assertSet("cardio.{$this->bikeItem->id}.time", '21:30');

    $first = $this->sets[0]->fresh();
    expect($first->weight)->toBe('42.50')
        ->and($first->reps)->toBe(12)
        ->and($first->is_completed)->toBeTrue()
        ->and($first->completed_at->timestamp)->toBe($at->timestamp)
        ->and($this->sets[1]->fresh()->is_warmup)->toBeTrue()
        ->and($this->sets[1]->fresh()->is_completed)->toBeFalse();

    $log = $this->bikeItem->cardio()->with('splits')->first();
    expect($log->duration_seconds)->toBe(1290)
        ->and($log->distance_m)->toBe(8400)
        ->and($log->splits->pluck('duration_seconds', 'number')->all())->toBe([1 => 310, 2 => 305])
        ->and($this->bikeItem->fresh()->completed_at->timestamp)->toBe($at->timestamp);
});

test('sending the same changes twice changes nothing', function () {
    $changes = ['sets' => [$this->sets[0]->id => ['reps' => 11, 'done' => true]]];

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $this->session])
        ->call('syncOffline', $changes)
        ->call('syncOffline', $changes);

    expect($this->sets[0]->fresh()->is_completed)->toBeTrue()
        ->and($this->sets[0]->fresh()->reps)->toBe(11);
});

test('a time from the phone outside the training is replaced by now', function () {
    $this->freezeTime();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $this->session])
        ->call('syncOffline', ['sets' => [$this->sets[0]->id => ['done' => true, 'at' => now()->addDay()->getTimestampMs()]]]);

    expect($this->sets[0]->fresh()->completed_at->timestamp)->toBe(now()->timestamp);
});

test('invalid values get an error and sets of another training are skipped', function () {
    $other = app(StartWorkoutSession::class)->handle(User::factory()->create(), Workout::factory()->create());
    $foreign = $other->exercises()->first()?->sets()->first();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $this->session])
        ->call('syncOffline', ['sets' => [
            $this->sets[0]->id => ['reps' => 'veel'],
            ...($foreign ? [$foreign->id => ['done' => true]] : []),
            999999 => ['done' => true],
        ]])
        ->assertHasErrors("values.{$this->sets[0]->id}.reps");

    if ($foreign) {
        expect($foreign->fresh()->is_completed)->toBeFalse();
    }
});

test('a record among the sent sets is announced', function () {
    // Eerdere training met 40 kg
    $this->session->exercises()->each(fn ($item) => $item->sets()->update(['is_completed' => true]));
    $this->session->finish();
    $this->travel(2)->days();
    $this->session = app(StartWorkoutSession::class)->handle($this->user, $this->workout);
    $this->sets = $this->session->exercises()->orderBy('position')->first()->sets()->orderBy('set_number')->get();

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $this->session])
        ->call('syncOffline', ['sets' => [$this->sets[0]->id => ['weight' => 60, 'reps' => 10, 'done' => true]]])
        ->assertDispatched('record');
});

test('nothing is saved when the training was already finished', function () {
    $component = Livewire::actingAs($this->user)->test(Run::class, ['session' => $this->session]);
    $this->session->finish();

    $component->call('syncOffline', ['sets' => [$this->sets[0]->id => ['done' => true]]])
        ->assertRedirect(route('sessions.summary', $this->session));

    expect($this->sets[0]->fresh()->is_completed)->toBeFalse();
});

test('the service worker keeps the training page for use without a connection', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("const PAGES = 'lukeboy-pages'")
        ->and($sw)->toContain('sessions')
        ->and(file_get_contents(resource_path('js/app.js')))->toContain("import './offline-training'");
});
