<?php

use App\Jobs\SendPushBroadcast;
use App\Jobs\SendRestOverPush;
use App\Livewire\Admin\PushMessages;
use App\Livewire\Profile\PushPreferences;
use App\Models\PushBroadcast;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\RestReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Minishlink\WebPush\VAPID;

uses(RefreshDatabase::class);

beforeEach(function () {
    $keys = VAPID::createVapidKeys();
    config(['services.webpush.public_key' => $keys['publicKey'], 'services.webpush.private_key' => $keys['privateKey']]);

    $this->user = User::factory()->create(['name' => 'Jan']);
    $endpoint = 'https://127.0.0.1:9/push/jan';
    $this->user->pushSubscriptions()->create([
        'endpoint' => $endpoint, 'endpoint_hash' => PushSubscription::hash($endpoint),
        'public_key' => 'k', 'auth_token' => 'a', 'content_encoding' => 'aes128gcm',
    ]);
});

test('both kinds of notifications are on by default and can be turned off', function () {
    expect($this->user->fresh())->push_rest->toBeTrue()->push_admin->toBeTrue();

    $this->actingAs($this->user)->get(route('profile.edit'))
        ->assertSee('Wat wil je ontvangen?')->assertSee('Berichten van de beheerder');

    Livewire::actingAs($this->user)->test(PushPreferences::class)
        ->set('push_rest', false)
        ->assertDispatched('saved')
        ->set('push_admin', false);

    expect($this->user->fresh())->push_rest->toBeFalse()->push_admin->toBeFalse();
});

test('no rest notification when that kind is off', function () {
    Queue::fake();

    RestReminder::schedule($this->user, 60, 'Volgende set', '/');
    Queue::assertPushed(SendRestOverPush::class, 1);

    $this->user->forceFill(['push_rest' => false])->save();
    RestReminder::schedule($this->user->fresh(), 60, 'Volgende set', '/');
    Queue::assertPushed(SendRestOverPush::class, 1);
});

test('no admin message when that kind is off', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->user->forceFill(['push_admin' => false])->save();

    Livewire::actingAs($admin)->test(PushMessages::class)
        ->assertSee('Bereikt 2 gebruikers · 0 met pushmelding')
        ->set('audience', 'user')
        ->assertSee('Jan (alleen in Berichten)');

    $broadcast = PushBroadcast::create(['user_id' => $admin->id, 'title' => 'A', 'body' => 'B']);
    (new SendPushBroadcast($broadcast->id))->handle();
    expect($broadcast->fresh())->users_count->toBe(2)->delivered_count->toBe(0)
        ->and($this->user->messages()->count())->toBe(1); // wel in Berichten
});

test('the migration adds and removes the columns', function () {
    $migration = require database_path('migrations/2026_11_14_220000_add_push_preferences_to_users_table.php');

    $migration->down();
    expect(Schema::hasColumns('users', ['push_rest', 'push_admin']))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumns('users', ['push_rest', 'push_admin']))->toBeTrue()
        ->and($this->user->fresh()->push_admin)->toBeTrue();
});
