<?php

use App\Actions\StartWorkoutSession;
use App\Jobs\SendRestOverPush;
use App\Livewire\Sessions\Run;
use App\Models\Exercise;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Support\RestReminder;
use App\Support\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Minishlink\WebPush\VAPID;

uses(RefreshDatabase::class);

beforeEach(function () {
    $keys = VAPID::createVapidKeys();
    config(['services.webpush.public_key' => $keys['publicKey'], 'services.webpush.private_key' => $keys['privateKey']]);

    $this->user = User::factory()->create();
});

/** Een geldig (maar nep) abonnement zoals een browser het geeft */
function fakeSubscription(string $endpoint = 'https://push.example.test/abc'): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $details = openssl_pkey_get_details($key)['ec'];
    $b64 = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    return [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => $b64("\x04".$details['x'].$details['y']), 'auth' => $b64(random_bytes(16))],
        'contentEncoding' => 'aes128gcm',
    ];
}

function runningPress(User $user, int $rest = 90): array
{
    $press = Exercise::factory()->create(['name' => 'Chest Press']);
    $workout = Workout::factory()->for($user)->create();
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $press->id, 'position' => 0, 'target_sets' => 3, 'target_reps' => 10, 'rest_seconds' => $rest]);
    $session = app(StartWorkoutSession::class)->handle($user, $workout);

    return [$session, $session->exercises()->first()->sets()->orderBy('set_number')->get()];
}

test('a device can turn notifications on and off', function () {
    $this->actingAs($this->user)->postJson(route('push.subscribe'), fakeSubscription())->assertOk();
    $this->postJson(route('push.subscribe'), fakeSubscription())->assertOk(); // zelfde endpoint: geen dubbele rij

    expect($this->user->pushSubscriptions()->count())->toBe(1);

    $this->postJson(route('push.subscribe'), ['endpoint' => 'http://unsafe.test/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])
        ->assertUnprocessable();

    $this->deleteJson(route('push.unsubscribe'), ['endpoint' => 'https://push.example.test/abc'])->assertOk();
    expect($this->user->pushSubscriptions()->count())->toBe(0);
});

test('a device that changes account moves to the new account', function () {
    $other = User::factory()->create();
    $this->actingAs($other)->postJson(route('push.subscribe'), fakeSubscription())->assertOk();
    $this->actingAs($this->user)->postJson(route('push.subscribe'), fakeSubscription())->assertOk();

    expect(PushSubscription::count())->toBe(1)
        ->and(PushSubscription::first()->user_id)->toBe($this->user->id);
});

test('ticking off a set schedules the push for the end of the rest', function () {
    Queue::fake();
    $this->actingAs($this->user)->postJson(route('push.subscribe'), fakeSubscription());
    [$session, $sets] = runningPress($this->user);

    Livewire::test(Run::class, ['session' => $session])
        ->call('toggleSet', $sets[0]->id)
        ->assertDispatched('rest-start', seconds: 90);

    Queue::assertPushed(SendRestOverPush::class, fn (SendRestOverPush $job) => $job->userId === $this->user->id
        && $job->body === 'Tijd voor set 2 van Chest Press.'
        && $job->url === route('sessions.run', $session)
        && $job->delay->diffInSeconds(now(), true) >= 89);
});

test('no push without a device or without keys', function () {
    Queue::fake();
    [$session, $sets] = runningPress($this->user);

    Livewire::actingAs($this->user)->test(Run::class, ['session' => $session])->call('toggleSet', $sets[0]->id);
    Queue::assertNothingPushed();

    config(['services.webpush.private_key' => null]);
    expect(WebPush::configured())->toBeFalse()
        ->and(WebPush::send($this->user, ['title' => 'x', 'body' => 'y']))->toBe(0);
});

test('only the latest rest sends, once; skipping, pausing or finishing cancels it', function () {
    Queue::fake();
    $this->actingAs($this->user)->postJson(route('push.subscribe'), fakeSubscription());
    [$session, $sets] = runningPress($this->user);
    $component = Livewire::test(Run::class, ['session' => $session]);

    $component->call('toggleSet', $sets[0]->id)->call('toggleSet', $sets[1]->id);
    [$first, $second] = Queue::pushed(SendRestOverPush::class)->all();

    expect(RestReminder::claim($this->user->id, $first->token))->toBeFalse()   // vervangen door de tweede rust
        ->and(RestReminder::claim($this->user->id, $second->token))->toBeTrue()
        ->and(RestReminder::claim($this->user->id, $second->token))->toBeFalse(); // maar één keer

    foreach (['skipRest', 'pause', 'finish'] as $action) {
        $component->call('resume');
        $component->call('toggleSet', $sets[2]->id)->call('toggleSet', $sets[2]->id); // uit en weer aan
        $job = Queue::pushed(SendRestOverPush::class)->last();
        $component->call($action);

        expect(RestReminder::claim($this->user->id, $job->token))->toBeFalse("na {$action}");
    }
});

test('sending encrypts for every device and removes nothing on a network error', function () {
    $this->actingAs($this->user)->postJson(route('push.subscribe'), fakeSubscription('https://127.0.0.1:9/push'));

    // Geen echte pushdienst bereikbaar: niet afgeleverd, maar ook geen crash en het abonnement blijft staan
    expect(WebPush::send($this->user, ['title' => 'Rust voorbij', 'body' => 'Test']))->toBe(0)
        ->and($this->user->pushSubscriptions()->count())->toBe(1);
});

test('the profile shows the setting and the page carries the public key', function () {
    $this->actingAs($this->user)->get(route('profile.edit'))->assertOk()
        ->assertSee('Melding na je rust')
        ->assertSee('<meta name="vapid-public-key" content="'.config('services.webpush.public_key').'">', false);
});

test('the vapid command prints two keys', function () {
    $this->artisan('fitness:vapid-keys')
        ->expectsOutputToContain('VAPID_PUBLIC_KEY=')
        ->expectsOutputToContain('VAPID_PRIVATE_KEY=')
        ->assertSuccessful();
});
