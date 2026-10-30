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

test('the menu counts the accounts of the last 7 days', function () {
    User::factory()->create(['created_at' => now()->subDays(8)]);
    User::factory()->count(2)->create(['created_at' => now()->subDays(6)]);

    expect($this->admin->newUsersCount())->toBe(2);
    $this->actingAs($this->admin)->get(route('admin.settings'))->assertSee('2 nieuw');

    // De lijst openen verandert niets aan de teller
    Livewire::actingAs($this->admin)->test(Users::class)->assertSee('Nieuw');
    expect($this->admin->newUsersCount())->toBe(2);

    // Een week later tellen ze niet meer mee
    $this->travel(2)->days();
    expect($this->admin->newUsersCount())->toBe(0);
    Livewire::actingAs($this->admin)->test(Users::class)->assertDontSee('Nieuw');
});

test('normal users never see a counter', function () {
    $user = User::factory()->create();
    User::factory()->create();

    expect($user->newUsersCount())->toBe(0);
});
