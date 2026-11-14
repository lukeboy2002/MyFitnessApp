<?php

use App\Jobs\SendPushBroadcast;
use App\Livewire\Admin\PushMessages;
use App\Livewire\Messages;
use App\Models\PushBroadcast;
use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create();
});

function message(User $user, array $attributes = []): UserMessage
{
    return $user->messages()->create(['title' => 'Nieuwe badges', 'body' => 'Kijk eens bij Badges!', 'url' => '/badges', ...$attributes]);
}

test('a sent message lands in the messages of every user, also without push', function () {
    $blocked = User::factory()->create(['banned_at' => now()]);

    $broadcast = PushBroadcast::create(['user_id' => $this->admin->id, 'title' => 'Onderhoud', 'body' => 'Vanavond offline', 'url' => '/']);
    (new SendPushBroadcast($broadcast->id))->handle();

    expect($this->user->messages()->sole())->title->toBe('Onderhoud')->read_at->toBeNull()
        ->and($blocked->messages()->count())->toBe(0)
        ->and($broadcast->fresh()->users_count)->toBe(2); // admin + gebruiker

    $one = PushBroadcast::create(['user_id' => $this->admin->id, 'to_everyone' => false, 'recipient_id' => $this->user->id, 'title' => 'Hoi', 'body' => 'Alleen voor jou']);
    (new SendPushBroadcast($one->id))->handle();

    expect($this->user->messages()->count())->toBe(2)
        ->and($this->admin->messages()->count())->toBe(1);
});

test('the bell shows the number of unread messages', function () {
    message($this->user);
    message($this->user);
    message($this->user, ['read_at' => now()]);

    expect($this->user->unreadMessagesCount())->toBe(2);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSee('{ unread: 2 }', false)
        ->assertSee(route('messages'));
});

test('the user reads, marks as unread, marks all as read and deletes messages', function () {
    $first = message($this->user, ['title' => 'Eerste']);
    $second = message($this->user, ['title' => 'Tweede']);

    $component = Livewire::actingAs($this->user)->test(Messages::class)
        ->assertSee(['Eerste', 'Tweede', 'Alles als gelezen markeren'])
        ->call('markRead', $first->id)
        ->assertDispatched('messages-changed', unread: 1);
    expect($first->fresh()->read_at)->not->toBeNull();

    $component->call('markUnread', $first->id);
    expect($first->fresh()->read_at)->toBeNull();

    $component->call('markAllRead')->assertDispatched('messages-changed', unread: 0)->assertDontSee('Alles als gelezen markeren');
    expect($this->user->unreadMessagesCount())->toBe(0);

    $component->call('delete', $second->id)->assertDontSee('Tweede');
    expect(UserMessage::find($second->id))->toBeNull();
});

test('tapping the push notification opens messages and marks it as read', function () {
    $message = message($this->user);

    $this->actingAs($this->user)->get(route('messages', ['read' => $message->id]))->assertOk()->assertSee('Nieuwe badges');

    expect($message->fresh()->read_at)->not->toBeNull();
});

test('nobody can touch the messages of someone else', function () {
    $other = message(User::factory()->create());

    Livewire::actingAs($this->user)->test(Messages::class, ['read' => $other->id])
        ->call('markRead', $other->id)
        ->call('delete', $other->id)
        ->assertDontSee('Nieuwe badges');

    expect($other->fresh())->not->toBeNull()->read_at->toBeNull();
});

test('the admin sees how many users read a message', function () {
    $broadcast = PushBroadcast::create(['user_id' => $this->admin->id, 'title' => 'Onderhoud', 'body' => 'x', 'users_count' => 2, 'sent_at' => now()]);
    message($this->user, ['push_broadcast_id' => $broadcast->id, 'read_at' => now()]);
    message($this->admin, ['push_broadcast_id' => $broadcast->id]);

    Livewire::actingAs($this->admin)->test(PushMessages::class)->assertSee('1 gelezen');
});

test('an empty page explains where messages come from', function () {
    $this->actingAs($this->user)->get(route('messages'))->assertSee('Geen berichten');
});

test('the migration creates and drops the table', function () {
    $migration = require database_path('migrations/2026_11_14_230000_create_user_messages_table.php');

    $migration->down();
    expect(Schema::hasTable('user_messages'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('user_messages'))->toBeTrue();
});
