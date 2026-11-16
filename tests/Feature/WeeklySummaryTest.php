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
    $this->travelTo('2026-11-16 08:00'); // maandag; vorige week = 9 t/m 15 november
    $this->user = User::factory()->create(['locale' => 'nl', 'weekly_goal_sessions' => 3, 'weekly_goal_minutes' => 180]);
});

function trained(User $user, string $start, int $minutes = 60, string $status = 'completed'): WorkoutSession
{
    return WorkoutSession::create(['user_id' => $user->id, 'name' => 'Push', 'started_at' => $start, 'duration_seconds' => $minutes * 60, 'status' => $status]);
}

test('on monday you get a summary of last week in messages', function () {
    trained($this->user, '2026-11-09 18:00', 75);
    trained($this->user, '2026-11-15 10:00', 50);
    trained($this->user, '2026-11-08 18:00');                 // week ervoor: telt niet
    trained($this->user, '2026-11-16 07:00');                 // deze week: telt niet
    trained($this->user, '2026-11-12 18:00', 60, 'cancelled'); // gestopt: telt niet

    $this->artisan('fitness:weekly-summaries')->assertSuccessful();

    expect($this->user->messages()->sole())
        ->title->toBe('Je week van 9 – 15 november')
        ->body->toBe("2 trainingen · 2u 5m\n2 van de 3 trainingen voor je doel.\n2u 5m van de 3u trainingstijd voor je doel.")
        ->url->toBe(route('progress.index', absolute: false));
});

test('a reached goal is mentioned, and the summary follows the language of the user', function () {
    $this->user->update(['locale' => 'de', 'weekly_goal_sessions' => 1, 'weekly_goal_minutes' => null]);
    trained($this->user, '2026-11-10 18:00', 45);

    $this->artisan('fitness:weekly-summaries');

    expect($this->user->messages()->sole())
        ->title->toBe('Deine Woche vom 9 – 15 November')
        ->body->toBe("1 Training · 45m\nZiel von 1 Trainings erreicht!");
});

test('no summary without trainings, when turned off, for a blocked user or twice for the same week', function () {
    $off = User::factory()->create(['push_weekly' => false]);
    trained($off, '2026-11-10 18:00');
    $blocked = User::factory()->create(['banned_at' => now()]);
    trained($blocked, '2026-11-10 18:00');
    $lazy = User::factory()->create();

    trained($this->user, '2026-11-10 18:00');
    $this->artisan('fitness:weekly-summaries');
    $this->artisan('fitness:weekly-summaries');

    expect($off->messages()->count())->toBe(0)
        ->and($blocked->messages()->count())->toBe(0)
        ->and($lazy->messages()->count())->toBe(0)
        ->and($this->user->messages()->count())->toBe(1);
});

test('the summary runs on monday at 8', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'fitness:weekly-summaries'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 8 * * 1');
});

test('the user can turn the weekly summary off', function () {
    Livewire::actingAs($this->user)->test(PushPreferences::class)
        ->assertSee('Weekoverzicht')
        ->set('push_weekly', false);

    expect($this->user->fresh()->push_weekly)->toBeFalse();
});

test('the migration adds and removes the column', function () {
    $migration = require database_path('migrations/2026_11_16_200000_add_push_weekly_to_users_table.php');

    $migration->down();
    expect(Schema::hasColumn('users', 'push_weekly'))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumn('users', 'push_weekly'))->toBeTrue();
});
