<?php

use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the scheduler cleans up failed jobs, reset links and old messages every night', function () {
    $commands = collect(app(Schedule::class)->events())->map->command->implode("\n");

    expect($commands)->toContain('queue:prune-failed --hours=168')
        ->toContain('auth:clear-resets')
        ->toContain('model:prune');
});

test('only read messages older than half a year are pruned', function () {
    $user = User::factory()->create();
    $make = fn (array $attributes) => tap($user->messages()->create(['title' => 'T', 'body' => 'B']))->forceFill($attributes)->save();

    $make(['read_at' => now()->subMonths(7), 'created_at' => now()->subMonths(7)]);          // weg
    $make(['read_at' => null, 'created_at' => now()->subMonths(7)]);                          // ongelezen: blijft
    $make(['read_at' => now()->subMonth(), 'created_at' => now()->subMonths(2)]);             // recent: blijft

    $this->artisan('model:prune', ['--model' => [UserMessage::class]])->assertSuccessful();

    expect($user->messages()->count())->toBe(2)
        ->and($user->messages()->whereNull('read_at')->count())->toBe(1);
});
