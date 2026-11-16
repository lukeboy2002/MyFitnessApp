<?php

use App\Livewire\Profile\PushPreferences;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-16 10:00');
    $this->user = User::factory()->create(['locale' => 'nl']);
});

function lastTrained(User $user, string $start, string $status = 'completed'): WorkoutSession
{
    return WorkoutSession::create(['user_id' => $user->id, 'name' => 'Push', 'started_at' => $start, 'duration_seconds' => 3600, 'status' => $status]);
}

test('after 10 days without training you get a friendly message', function () {
    lastTrained($this->user, '2026-10-20 18:00');
    lastTrained($this->user, '2026-11-05 18:00'); // laatste: 11 dagen geleden

    $this->artisan('fitness:inactivity-reminders')->assertSuccessful();

    expect($this->user->messages()->sole())
        ->title->toBe('We missen je!')
        ->body->toBe('Je laatste training was 11 dagen geleden. Zin om vandaag weer te trainen? Ook een korte training telt.')
        ->url->toBe(route('workouts.index', absolute: false));
});

test('only one message per period without training, a new one after training again', function () {
    lastTrained($this->user, '2026-11-01 18:00');

    $this->artisan('fitness:inactivity-reminders');
    $this->travel(1)->day();
    $this->artisan('fitness:inactivity-reminders');
    expect($this->user->messages()->count())->toBe(1);

    lastTrained($this->user, now()->toDateTimeString());
    $this->travel(11)->days();
    $this->artisan('fitness:inactivity-reminders');
    expect($this->user->messages()->count())->toBe(2);
});

test('no message when trained recently, never trained, training now, turned off or blocked', function () {
    lastTrained($this->user, '2026-11-10 18:00'); // 6 dagen geleden
    $never = User::factory()->create();

    $running = User::factory()->create();
    lastTrained($running, '2026-10-01 18:00');
    lastTrained($running, now()->toDateTimeString(), 'active');

    $off = User::factory()->create(['push_inactive' => false]);
    lastTrained($off, '2026-10-01 18:00');
    $blocked = User::factory()->create(['banned_at' => now()]);
    lastTrained($blocked, '2026-10-01 18:00');
    $stopped = User::factory()->create();
    lastTrained($stopped, '2026-11-15 18:00', 'cancelled'); // telt niet als training, en verder nooit getraind

    $this->artisan('fitness:inactivity-reminders');

    foreach ([$this->user, $never, $running, $off, $blocked, $stopped] as $user) {
        expect($user->messages()->count())->toBe(0);
    }
});

test('the check runs every day at 10', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'fitness:inactivity-reminders'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 10 * * *');
});

test('the user can turn it off', function () {
    Livewire::actingAs($this->user)->test(PushPreferences::class)
        ->assertSee('We missen je')
        ->set('push_inactive', false);

    expect($this->user->fresh()->push_inactive)->toBeFalse();
});

test('the migration adds and removes the columns', function () {
    $migration = require database_path('migrations/2026_11_16_210000_add_inactivity_reminder_to_users_table.php');

    $migration->down();
    expect(Schema::hasColumns('users', ['push_inactive', 'inactive_reminded_at']))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumns('users', ['push_inactive', 'inactive_reminded_at']))->toBeTrue();
});
