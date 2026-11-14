<?php

use App\Livewire\Admin\Users;
use App\Models\User;
use App\Notifications\AccountBanned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['email' => 'jan@example.com']);
});

test('the admin blocks a user for a week with a reason and the user gets a mail', function () {
    $this->freezeTime();

    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('startBan', $this->user->id)
        ->assertSet('showBan', true)
        ->set('ban_duration', '7')
        ->set('ban_reason', 'Spam in de notities')
        ->call('ban')
        ->assertHasNoErrors()
        ->assertSet('showBan', false)
        ->assertSee('Geblokkeerd tot');

    $user = $this->user->fresh();
    expect($user->isBanned())->toBeTrue()
        ->and($user->banned_until->toDateTimeString())->toBe(now()->addDays(7)->toDateTimeString())
        ->and($user->ban_reason)->toBe('Spam in de notities');

    Notification::assertSentTo($user, AccountBanned::class);

    $mail = (new AccountBanned)->toMail($user);
    expect($mail->subject)->toBe('Je account is geblokkeerd')
        ->and(implode(' ', $mail->introLines))->toContain('Reden: Spam in de notities');
});

test('the admin blocks a user indefinitely or until a date', function () {
    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('startBan', $this->user->id)
        ->set('ban_duration', 'forever')
        ->call('ban');

    expect($this->user->fresh())->isBanned()->toBeTrue()
        ->and($this->user->fresh()->banned_until)->toBeNull();

    $other = User::factory()->create();
    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('startBan', $other->id)
        ->set('ban_duration', 'date')
        ->set('ban_until', today()->format('Y-m-d'))
        ->call('ban')
        ->assertHasErrors('ban_until')
        ->set('ban_until', today()->addDays(3)->format('Y-m-d'))
        ->call('ban')
        ->assertHasNoErrors();

    expect($other->fresh()->banned_until->toDateString())->toBe(today()->addDays(3)->toDateString());
});

test('a blocked user cannot log in and sees why', function () {
    $this->user->forceFill(['banned_at' => now(), 'banned_until' => null, 'ban_reason' => 'Spam'])->save();

    $this->post(route('login'), ['login' => 'jan@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'Je account is voor onbepaalde tijd geblokkeerd. Reden: Spam']);

    $this->assertGuest();
});

test('a logged in user is logged out at once when blocked', function () {
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

    $this->user->forceFill(['banned_at' => now(), 'banned_until' => now()->addDay()])->save();

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('banned');
    $this->assertGuest();

    $this->get(route('login'))->assertSee('Je account is geblokkeerd tot');
});

test('blocking removes the stored sessions of the user', function () {
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()]);

    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('startBan', $this->user->id)
        ->call('ban');

    expect(DB::table('sessions')->where('user_id', $this->user->id)->exists())->toBeFalse();
});

test('a temporary block ends by itself and can be lifted earlier', function () {
    $this->user->forceFill(['banned_at' => now(), 'banned_until' => now()->addDay()])->save();
    expect($this->user->fresh()->isBanned())->toBeTrue();

    $this->travel(2)->days();
    expect($this->user->fresh()->isBanned())->toBeFalse();
    $this->post(route('login'), ['login' => 'jan@example.com', 'password' => 'password']);
    $this->assertAuthenticatedAs($this->user);

    auth()->logout();
    $this->user->forceFill(['banned_at' => now(), 'banned_until' => null])->save();

    Livewire::actingAs($this->admin)->test(Users::class)->call('unban', $this->user->id);

    expect($this->user->fresh())->isBanned()->toBeFalse()
        ->banned_at->toBeNull()
        ->ban_reason->toBeNull();
});

test('admins and yourself cannot be blocked', function () {
    $otherAdmin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($this->admin)->test(Users::class)->call('startBan', $this->admin->id)->assertForbidden();
    Livewire::actingAs($this->admin)->test(Users::class)->call('startBan', $otherAdmin->id)->assertForbidden();
    expect(fn () => Livewire::actingAs($this->admin)->test(Users::class)->set('banning', $otherAdmin->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect($otherAdmin->fresh()->isBanned())->toBeFalse();
});

test('a normal user cannot block anyone', function () {
    $other = User::factory()->create();

    Livewire::actingAs($other)->test(Users::class)->assertForbidden();
});

test('the migration adds and removes the ban columns', function () {
    $migration = require database_path('migrations/2026_11_14_200000_add_ban_to_users_table.php');

    $migration->down();
    expect(Schema::hasColumns('users', ['banned_at', 'banned_until', 'ban_reason']))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumns('users', ['banned_at', 'banned_until', 'ban_reason']))->toBeTrue();
});
