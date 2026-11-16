<?php

use App\Jobs\SendPushBroadcast;
use App\Livewire\Admin\PushMessages;
use App\Livewire\Admin\Users;
use App\Models\PushBroadcast;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Minishlink\WebPush\VAPID;

uses(RefreshDatabase::class);

beforeEach(function () {
    $keys = VAPID::createVapidKeys();
    config(['services.webpush.public_key' => $keys['publicKey'], 'services.webpush.private_key' => $keys['privateKey']]);

    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->jan = withPush(User::factory()->create(['name' => 'Jan']));
    $this->piet = withPush(User::factory()->create(['name' => 'Piet']));
    $this->without = User::factory()->create(['name' => 'Zonder push']);
});

function withPush(User $user): User
{
    $endpoint = 'https://127.0.0.1:9/push/'.$user->id;
    $user->pushSubscriptions()->create([
        'endpoint' => $endpoint, 'endpoint_hash' => PushSubscription::hash($endpoint),
        'public_key' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
        'auth_token' => 'tBHItJI5svbpez7KI4CCXg', 'content_encoding' => 'aes128gcm',
    ]);

    return $user;
}

test('the admin sends a push notification to everyone', function () {
    Queue::fake();

    Livewire::actingAs($this->admin)->test(PushMessages::class)
        ->assertSee('Bereikt 4 gebruikers · 2 met pushmelding')
        ->set('title', 'Nieuwe badges')
        ->set('body', 'Kijk eens bij Badges!')
        ->set('link', 'badges')
        ->call('send')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.push'));

    $broadcast = PushBroadcast::sole();
    expect($broadcast)
        ->to_everyone->toBeTrue()
        ->user_id->toBe($this->admin->id)
        ->url->toBe('/badges')
        ->sent_at->toBeNull();

    Queue::assertPushed(SendPushBroadcast::class, fn ($job) => $job->broadcastId === $broadcast->id);
});

test('the admin sends a push notification to one user with push turned on', function () {
    Queue::fake();

    Livewire::actingAs($this->admin)->test(PushMessages::class)
        ->set('audience', 'user')
        ->assertSee('Jan')->assertSee('Zonder push (alleen in Berichten)')
        ->set('title', 'Hoi')->set('body', 'Goed bezig')
        ->call('send')
        ->assertHasErrors(['recipient_id' => 'required_if'])
        ->set('recipient_id', '99999')
        ->call('send')
        ->assertHasErrors('recipient_id')
        ->set('recipient_id', (string) $this->without->id)
        ->assertSee('Bereikt 1 gebruiker · 0 met pushmelding')
        ->set('recipient_id', (string) $this->jan->id)
        ->assertSee('Bereikt 1 gebruiker · 1 met pushmelding')
        ->call('send')
        ->assertHasNoErrors();

    expect(PushBroadcast::sole())
        ->to_everyone->toBeFalse()
        ->recipient_id->toBe($this->jan->id)
        ->recipient_name->toBe('Jan')
        ->url->toBeNull();
});

test('the job sends to the right users and keeps the result', function () {
    $this->piet->forceFill(['banned_at' => now(), 'banned_until' => null])->save();

    $all = PushBroadcast::create(['user_id' => $this->admin->id, 'title' => 'A', 'body' => 'B']);
    (new SendPushBroadcast($all->id))->handle();

    // Admin, Jan en Zonder push krijgen het in Berichten (Piet is geblokkeerd); geen echte pushdienst in de test: niets afgeleverd
    expect($all->fresh())->users_count->toBe(3)->delivered_count->toBe(0)->sent_at->not->toBeNull()
        ->and($this->piet->messages()->count())->toBe(0);

    $one = PushBroadcast::create(['user_id' => $this->admin->id, 'to_everyone' => false, 'recipient_id' => $this->without->id, 'title' => 'A', 'body' => 'B']);
    (new SendPushBroadcast($one->id))->handle();
    expect($one->fresh()->users_count)->toBe(1)
        ->and($this->without->messages()->count())->toBe(2);

    $sentAt = $all->fresh()->sent_at;
    (new SendPushBroadcast($all->id))->handle(); // nooit twee keer
    expect($all->fresh()->sent_at->equalTo($sentAt))->toBeTrue();
});

test('the history shows sent notifications', function () {
    PushBroadcast::create(['user_id' => $this->admin->id, 'title' => 'Onderhoud', 'body' => 'Vanavond even offline', 'users_count' => 2, 'delivered_count' => 3, 'sent_at' => now()]);
    PushBroadcast::create(['user_id' => $this->admin->id, 'to_everyone' => false, 'recipient_id' => $this->jan->id, 'recipient_name' => 'Jan', 'title' => 'Persoonlijk', 'body' => 'Hoi Jan']);

    Livewire::actingAs($this->admin)->test(PushMessages::class)
        ->assertSee('Onderhoud')->assertSee('3 apparaten bereikt')
        ->assertSee('Persoonlijk')->assertSee('Wordt verstuurd');
});

test('only admins can use the page', function () {
    $this->actingAs($this->jan)->get(route('admin.push'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.push'))->assertOk()->assertSee('Pushmeldingen');
});

test('the migration creates and drops the table', function () {
    $migration = require database_path('migrations/2026_11_14_210000_create_push_broadcasts_table.php');

    $migration->down();
    expect(Schema::hasTable('push_broadcasts'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('push_broadcasts'))->toBeTrue();
});

test('the users page has a send message button that opens push notifications with that user chosen', function () {
    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('toggle', $this->jan->id)
        ->assertSee(route('admin.push', ['to' => $this->jan->id]), false)
        ->assertSee('Bericht sturen');

    $this->actingAs($this->admin)->get(route('admin.push', ['to' => $this->jan->id]))->assertOk();

    Livewire::withQueryParams(['to' => $this->jan->id])->actingAs($this->admin)->test(PushMessages::class)
        ->assertSet('audience', 'user')
        ->assertSet('recipient_id', (string) $this->jan->id);
});

test('a blocked or unknown user in the link is not chosen', function () {
    $blocked = User::factory()->create(['banned_at' => now()]);

    foreach ([$blocked->id, 99999] as $id) {
        Livewire::withQueryParams(['to' => $id])->actingAs($this->admin)->test(PushMessages::class)
            ->assertSet('audience', 'everyone')
            ->assertSet('recipient_id', null);
    }
});
