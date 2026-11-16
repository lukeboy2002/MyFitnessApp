<?php

use App\Livewire\Profile\PushPreferences;
use App\Models\ScheduledWorkout;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['locale' => 'nl']);
    $this->workout = Workout::factory()->for($this->user)->create(['name' => 'Workout A']);
});

function plan(User $user, Workout $workout, $date = null, array $attributes = []): ScheduledWorkout
{
    return ScheduledWorkout::create(['user_id' => $user->id, 'workout_id' => $workout->id, 'scheduled_on' => $date ?? today(), ...$attributes]);
}

test('a training planned for today gives a reminder in messages', function () {
    plan($this->user, $this->workout);
    plan($this->user, $this->workout, today()->addDay()); // morgen: nog niet

    $this->artisan('fitness:training-reminders')->assertSuccessful();

    expect($this->user->messages()->sole())
        ->title->toBe('Vandaag op het schema')
        ->body->toBe('Workout A staat vandaag gepland. Succes!')
        ->url->toBe(route('workouts.show', $this->workout, absolute: false));
});

test('two trainings on one day give one reminder that links to the calendar', function () {
    plan($this->user, $this->workout);
    plan($this->user, Workout::factory()->for($this->user)->create(['name' => 'Workout B']));

    $this->artisan('fitness:training-reminders');

    expect($this->user->messages()->sole())
        ->body->toBe('Workout A en Workout B staan vandaag gepland. Succes!')
        ->url->toBe(route('calendar', absolute: false));
});

test('the reminder is in the language of the user', function () {
    $this->user->update(['locale' => 'de']);
    plan($this->user, $this->workout);

    $this->artisan('fitness:training-reminders');

    expect($this->user->messages()->sole()->title)->toBe('Heute auf dem Plan');
});

test('no reminder when the training is done, turned off, the user is blocked or it already ran', function () {
    $done = User::factory()->create();
    $session = WorkoutSession::create(['user_id' => $done->id, 'name' => 'x', 'started_at' => now(), 'status' => 'completed']);
    plan($done, Workout::factory()->for($done)->create(), null, ['workout_session_id' => $session->id]);

    $off = User::factory()->create(['push_reminder' => false]);
    plan($off, Workout::factory()->for($off)->create());

    $blocked = User::factory()->create(['banned_at' => now()]);
    plan($blocked, Workout::factory()->for($blocked)->create());

    plan($this->user, $this->workout);
    $this->artisan('fitness:training-reminders');
    $this->artisan('fitness:training-reminders'); // tweede keer op dezelfde dag

    expect($done->messages()->count())->toBe(0)
        ->and($off->messages()->count())->toBe(0)
        ->and($blocked->messages()->count())->toBe(0)
        ->and($this->user->messages()->count())->toBe(1);
});

test('the reminder runs every morning at 7', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'fitness:training-reminders'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 7 * * *');
});

test('tapping the notification marks it as read and opens the workout', function () {
    plan($this->user, $this->workout);
    $this->artisan('fitness:training-reminders');
    $message = $this->user->messages()->sole();

    $this->actingAs($this->user)->get(route('messages', ['read' => $message->id, 'go' => 1]))
        ->assertRedirect(route('workouts.show', $this->workout, absolute: false));

    expect($message->fresh()->read_at)->not->toBeNull();
});

test('the user can turn the reminder off', function () {
    Livewire::actingAs($this->user)->test(PushPreferences::class)
        ->assertSee('Trainingsherinnering')
        ->set('push_reminder', false);

    expect($this->user->fresh()->push_reminder)->toBeFalse();
});

test('the migration adds and removes the column', function () {
    $migration = require database_path('migrations/2026_11_16_190000_add_push_reminder_to_users_table.php');

    $migration->down();
    expect(Schema::hasColumn('users', 'push_reminder'))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumn('users', 'push_reminder'))->toBeTrue();
});
