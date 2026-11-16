<?php

use App\Actions\StartWorkoutSession;
use App\Livewire\Sessions\Run;
use App\Models\Badge;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['locale' => 'nl']);
    $this->press = Exercise::factory()->create(['name' => 'Chest Press']);
    $this->workout = Workout::factory()->for($this->user)->create(['name' => 'Push']);
    WorkoutExercise::factory()->for($this->workout)->create(['exercise_id' => $this->press->id, 'position' => 0, 'target_sets' => 1, 'target_reps' => 10, 'target_weight' => 40]);
});

/** Training starten met één chest-press-set [kg, reps] en (via het trainingsscherm) afronden */
function finishWith(float $weight, int $reps): WorkoutSession
{
    test()->travel(1)->day(); // elke training op een eigen dag
    $session = app(StartWorkoutSession::class)->handle(test()->user, test()->workout);
    $item = $session->exercises()->sole();
    $item->sets()->delete();
    $item->sets()->create(['set_number' => 1, 'weight' => $weight, 'reps' => $reps, 'is_completed' => true]);

    Livewire::actingAs(test()->user)->test(Run::class, ['session' => $session])->call('finish');

    return $session->refresh();
}

test('a new record after finishing a training ends up in messages', function () {
    Badge::query()->update(['is_active' => false]);
    finishWith(40, 10);
    expect($this->user->messages()->count())->toBe(0); // eerste keer: nog geen records

    $session = finishWith(45, 10);

    expect($this->user->messages()->sole())
        ->title->toBe('1 nieuw record!')
        ->body->toBe("In je training Push\n\n1 nieuw record!\n• Chest Press: 45 kg × 10")
        ->url->toBe(route('sessions.summary', $session, absolute: false))
        ->read_at->toBeNull();
});

test('a new badge after finishing a training ends up in messages', function () {
    expect(Badge::where('is_active', true)->exists())->toBeTrue();

    finishWith(40, 10);

    $message = $this->user->messages()->sole();
    expect($message->title)->toStartWith('Nieuwe badge')
        ->and($message->body)->toContain('• ');
});

test('no message when nothing new was reached', function () {
    Badge::query()->update(['is_active' => false]);
    finishWith(40, 10);
    finishWith(40, 8);

    expect($this->user->messages()->count())->toBe(0);
});
