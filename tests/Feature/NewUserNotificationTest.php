<?php

use App\Listeners\NotifyAdminsOfNewUser;
use App\Livewire\Admin\Users;
use App\Models\User;
use App\Notifications\NewUserRegistered;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Antoine']);
});

test('the listener is registered for new registrations', function () {
    Event::fake();

    Event::assertListening(Registered::class, NotifyAdminsOfNewUser::class);
});

test('all admins get a mail when someone registers', function () {
    Notification::fake();
    $otherAdmin = User::factory()->create(['is_admin' => true]);
    $normal = User::factory()->create();

    $new = User::factory()->create(['name' => 'Nieuwe Sporter']);
    event(new Registered($new));

    Notification::assertSentTo([$this->admin, $otherAdmin], NewUserRegistered::class, fn ($notification) => $notification->user->is($new));
    Notification::assertNotSentTo([$normal, $new], NewUserRegistered::class);

    $mail = (new NewUserRegistered($new))->toMail($this->admin);
    expect($mail->subject)->toBe('Nieuwe gebruiker: Nieuwe Sporter')
        ->and($mail->actionUrl)->toBe(route('admin.users'));
});

test('a new account stays new until an admin clicks it', function () {
    $old = User::factory()->create(['seen_by_admin_at' => now()->subMonth()]);
    $first = User::factory()->create(['name' => 'Eerste Nieuwe', 'username' => 'eerste']);
    User::factory()->create();

    expect($this->admin->newUsersCount())->toBe(2);
    $this->actingAs($this->admin)->get(route('admin.settings'))->assertSee('2 nieuw');

    // De lijst openen verandert niets, ook niet na een week
    $this->travel(10)->days();
    Livewire::actingAs($this->admin)->test(Users::class)->assertSee('Nieuw');
    expect($this->admin->newUsersCount())->toBe(2);

    // Aanklikken: details open en gezien
    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('toggle', $first->id)
        ->assertSet('open', $first->id)
        ->assertSee(['Gebruikersnaam', 'eerste', 'Aangemeld']);

    expect($first->fresh()->isNew())->toBeFalse()
        ->and($old->fresh()->isNew())->toBeFalse()
        ->and($this->admin->newUsersCount())->toBe(1);

    // Je eigen account aanklikken telt niet
    Livewire::actingAs($this->admin)->test(Users::class)->call('toggle', $this->admin->id);
    expect($this->admin->fresh()->seen_by_admin_at)->toBeNull();
});

test('only admins can open the details', function () {
    $other = User::factory()->create();

    Livewire::actingAs(User::factory()->create())->test(Users::class)->assertForbidden();
    expect($other->fresh()->isNew())->toBeTrue();
});

test('the migration marks existing accounts as seen', function () {
    $migration = require database_path('migrations/2026_11_14_190000_add_seen_by_admin_at_to_users_table.php');
    $migration->down();
    $migration->up();

    expect(User::whereNull('seen_by_admin_at')->count())->toBe(0);
});

test('normal users never see a counter', function () {
    $user = User::factory()->create();
    User::factory()->create();

    expect($user->newUsersCount())->toBe(0);
});
