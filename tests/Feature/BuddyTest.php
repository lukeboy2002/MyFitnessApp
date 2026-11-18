<?php

use App\Actions\DeleteUserAccount;
use App\Actions\ShareWorkout;
use App\Livewire\Buddies\Index;
use App\Livewire\Profile\PublicProfile;
use App\Livewire\Profile\PushPreferences;
use App\Livewire\Workouts\Index as WorkoutsIndex;
use App\Livewire\Workouts\Show;
use App\Models\Buddy;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\SharedWorkout;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->antoine = User::factory()->create(['name' => 'Antoine', 'username' => 'antoine', 'email' => 'antoine@example.com', 'profile_public' => true]);
    $this->peter = User::factory()->create(['name' => 'Peter', 'username' => 'peter', 'email' => 'Peter@Example.com', 'locale' => 'nl', 'profile_public' => true]);
});

function makeBuddies(User $a, User $b): Buddy
{
    return Buddy::create(['user_id' => $a->id, 'buddy_id' => $b->id, 'accepted_at' => now()]);
}

test('the buddies page renders and shows your username', function () {
    $this->actingAs($this->antoine)->get(route('buddies'))->assertOk()->assertSee('@antoine');
});

test('you invite someone by username or email and they accept', function () {
    Livewire::actingAs($this->antoine)->test(Index::class)
        ->set('login', '@Peter')->call('invite')
        ->assertSet('message', __('If this account exists, your invitation has been sent.'));

    $invite = Buddy::sole();
    expect($invite->buddy_id)->toBe($this->peter->id)->and($invite->isAccepted())->toBeFalse();
    expect($this->peter->messages()->sole()->title)->toBe('Uitnodiging als vriend');

    // Nog een keer uitnodigen (op e-mail, andere hoofdletters) geeft geen tweede uitnodiging of bericht
    Livewire::actingAs($this->antoine)->test(Index::class)->set('login', 'peter@example.com')->call('invite');
    expect(Buddy::count())->toBe(1)->and($this->peter->messages()->count())->toBe(1);

    expect($this->peter->openBuddyInvitesCount())->toBe(1);
    Livewire::actingAs($this->peter)->test(Index::class)
        ->assertSee('Antoine')
        ->call('accept', $invite->id);

    expect($invite->fresh()->isAccepted())->toBeTrue()
        ->and(Buddy::areBuddies($this->peter, $this->antoine))->toBeTrue()
        ->and($this->antoine->buddies()->pluck('id')->all())->toBe([$this->peter->id])
        ->and($this->antoine->messages()->sole()->title)->toBe('Nieuwe vriend');
});

test('an unknown account gives the same message and nothing happens', function () {
    Livewire::actingAs($this->antoine)->test(Index::class)
        ->set('login', 'bestaat-niet')->call('invite')
        ->assertSet('message', __('If this account exists, your invitation has been sent.'));

    Livewire::actingAs($this->antoine)->test(Index::class)->set('login', 'antoine')->call('invite');

    expect(Buddy::count())->toBe(0);
});

test('inviting someone who already invited you makes you buddies', function () {
    Buddy::create(['user_id' => $this->peter->id, 'buddy_id' => $this->antoine->id]);

    Livewire::actingAs($this->antoine)->test(Index::class)->set('login', 'peter')->call('invite');

    expect(Buddy::sole()->isAccepted())->toBeTrue();
});

test('invitations are limited per hour', function () {
    RateLimiter::clear('buddy-invite:'.$this->antoine->id);
    $component = Livewire::actingAs($this->antoine)->test(Index::class);

    foreach (range(1, Index::PER_HOUR) as $i) {
        $component->set('login', 'nobody'.$i)->call('invite')->assertHasNoErrors();
    }

    $component->set('login', 'peter')->call('invite')->assertHasErrors('login');
    expect(Buddy::count())->toBe(0);
});

test('you can decline, withdraw and remove', function () {
    $invite = Buddy::create(['user_id' => $this->antoine->id, 'buddy_id' => $this->peter->id]);
    Livewire::actingAs($this->peter)->test(Index::class)->call('decline', $invite->id);
    expect(Buddy::count())->toBe(0);

    $invite = Buddy::create(['user_id' => $this->antoine->id, 'buddy_id' => $this->peter->id]);
    Livewire::actingAs($this->antoine)->test(Index::class)->assertSee(__('waiting for an answer'))->call('decline', $invite->id);
    expect(Buddy::count())->toBe(0);

    $buddy = makeBuddies($this->antoine, $this->peter);
    Livewire::actingAs($this->peter)->test(Index::class)->call('remove', $buddy->id);
    expect(Buddy::count())->toBe(0);

    // Iemand anders kan jouw rijen niet aanraken
    $buddy = makeBuddies($this->antoine, $this->peter);
    Livewire::actingAs(User::factory()->create())->test(Index::class)->call('remove', $buddy->id)->assertNotFound();
});

test('you share a workout with a buddy and they add it as their own copy', function () {
    makeBuddies($this->antoine, $this->peter);

    $standard = Exercise::factory()->create(['user_id' => null, 'name' => 'Chest Press']);
    $own = Exercise::factory()->create(['user_id' => $this->antoine->id, 'name' => 'Mijn curl', 'equipment_id' => Equipment::standardId('dumbbell')]);
    $own->syncMuscleGroups(MuscleGroup::whereNull('user_id')->where('slug', 'biceps')->value('id'));

    $workout = Workout::factory()->for($this->antoine)->create(['name' => 'Push']);
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $standard->id, 'position' => 0, 'target_reps' => 8]);
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $own->id, 'position' => 1]);

    Livewire::actingAs($this->antoine)->test(Show::class, ['workout' => $workout])
        ->call('openShare')
        ->assertSee('Mijn curl')
        ->set('shareTo', [$this->peter->id])
        ->set('shareMessage', 'Probeer maar!')
        ->call('share')
        ->assertHasNoErrors();

    $share = SharedWorkout::sole();
    expect($share->recipient_id)->toBe($this->peter->id)->and($share->message)->toBe('Probeer maar!')
        ->and($this->peter->messages()->sole()->title)->toBe('Antoine heeft een workout gedeeld');

    // Peter ziet hem onder Ontvangen en mag hem bekijken, maar niet starten of wijzigen
    Livewire::actingAs($this->peter)->test(WorkoutsIndex::class, ['showReceived' => true])->assertSee('Push')->assertSee('Probeer maar!');
    $this->actingAs($this->peter)->get(route('workouts.show', $workout))->assertOk()->assertSee('Mijn curl')->assertDontSee(route('exercises.show', $own));
    $this->actingAs($this->peter)->get(route('workouts.edit', $workout))->assertForbidden();

    Livewire::actingAs($this->peter)->test(WorkoutsIndex::class)->call('acceptShare', $share->id)->assertRedirect();

    $copy = Workout::where('user_id', $this->peter->id)->sole();
    $items = $copy->exercises()->with('exercise.muscleGroups')->orderBy('position')->get();
    expect($copy->name)->toBe('Push')
        ->and($items[0]->exercise_id)->toBe($standard->id)
        ->and($items[0]->target_reps)->toBe(8)
        ->and($items[1]->exercise->user_id)->toBe($this->peter->id)
        ->and($items[1]->exercise->name)->toBe('Mijn curl')
        ->and($items[1]->exercise->equipment_id)->toBe(Equipment::standardId('dumbbell'))
        ->and($items[1]->exercise->muscleGroups->pluck('slug')->all())->toBe(['biceps'])
        ->and($share->fresh()->copy_workout_id)->toBe($copy->id);

    // Na overnemen is het origineel niet meer zichtbaar voor Peter; een tweede keer delen hergebruikt de oefening
    $this->actingAs($this->peter)->get(route('workouts.show', $workout))->assertForbidden();
    app(ShareWorkout::class)->handle($this->antoine, $workout, collect([$this->peter]));
    Livewire::actingAs($this->peter)->test(WorkoutsIndex::class)->call('acceptShare', SharedWorkout::open()->sole()->id);
    expect(Exercise::where('user_id', $this->peter->id)->count())->toBe(1);
});

test('you can only share your own workouts with buddies', function () {
    $stranger = User::factory()->create();
    $workout = Workout::factory()->for($this->antoine)->create();

    expect(app(ShareWorkout::class)->handle($this->antoine, $workout, collect([$stranger])))->toBe(0);

    Livewire::actingAs($this->antoine)->test(Show::class, ['workout' => $workout])
        ->call('openShare')
        ->assertSee(__('You have no buddies yet. Invite someone first; then you can send them your workouts.'))
        ->set('shareTo', [$stranger->id])->call('share');
    expect(SharedWorkout::count())->toBe(0);

    Livewire::actingAs($this->peter)->test(Show::class, ['workout' => Workout::factory()->for($this->peter)->create()]);
    $this->actingAs($this->peter)->get(route('workouts.show', $workout))->assertForbidden();
});

test('declining removes it from Received', function () {
    makeBuddies($this->antoine, $this->peter);
    $workout = Workout::factory()->for($this->antoine)->create(['name' => 'Pull']);
    app(ShareWorkout::class)->handle($this->antoine, $workout, collect([$this->peter]));
    $share = SharedWorkout::sole();

    Livewire::actingAs($this->antoine)->test(WorkoutsIndex::class)->call('declineShare', $share->id)->assertNotFound();
    Livewire::actingAs($this->peter)->test(WorkoutsIndex::class)->call('declineShare', $share->id)->assertRedirect();

    expect($share->fresh()->declined_at)->not->toBeNull()
        ->and($this->peter->fresh()->openSharedCount())->toBe(0)
        ->and(Workout::where('user_id', $this->peter->id)->exists())->toBeFalse();
});

test('push for buddies can be switched off', function () {
    Livewire::actingAs($this->peter)->test(PushPreferences::class)->set('push_buddies', false);

    expect($this->peter->fresh()->push_buddies)->toBeFalse();
});

test('deleting an account removes buddies and shares', function () {
    makeBuddies($this->antoine, $this->peter);
    $workout = Workout::factory()->for($this->antoine)->create();
    app(ShareWorkout::class)->handle($this->antoine, $workout, collect([$this->peter]));

    app(DeleteUserAccount::class)->handle($this->antoine);

    expect(Buddy::count())->toBe(0)->and(SharedWorkout::count())->toBe(0);
});

test('only public profiles can be found and invited', function () {
    $hidden = User::factory()->create(['name' => 'Petra', 'username' => 'petra']);
    User::factory()->create(['name' => 'Pieter Banned', 'username' => 'pieterb', 'profile_public' => true, 'banned_at' => now()]);

    $page = Livewire::actingAs($this->antoine)->test(Index::class)->set('login', 'pe');
    expect($page->get('suggestions')->pluck('username')->all())->toBe(['peter']);

    // Op het begin van het e-mailadres, en jezelf zie je nooit
    $page->set('login', 'peter@ex');
    expect($page->get('suggestions')->pluck('username')->all())->toBe(['peter']);
    $page->set('login', 'antoine');
    expect($page->get('suggestions'))->toBeEmpty();

    // Een privéprofiel uitnodigen (exacte naam of id) doet niets
    $page->set('login', 'petra')->call('invite');
    expect(Buddy::count())->toBe(0);
    Livewire::actingAs($this->antoine)->test(Index::class)->call('inviteUser', $hidden->id)->assertNotFound();

    // Via een suggestie uitnodigen; daarna verschijnt Peter niet meer
    $page = Livewire::actingAs($this->antoine)->test(Index::class)
        ->call('inviteUser', $this->peter->id)
        ->assertSet('message', __('Invitation sent to :name.', ['name' => 'Peter']))
        ->set('login', 'pe');
    expect($page->get('suggestions'))->toBeEmpty();
    expect(Buddy::sole()->buddy_id)->toBe($this->peter->id);
});

test('without a public profile you cannot search or invite', function () {
    $this->antoine->forceFill(['profile_public' => false])->save();

    $page = Livewire::actingAs($this->antoine)->test(Index::class)
        ->assertSee(__('Make your profile public first. Then you can find and invite others, and receive workouts.'))
        ->set('login', 'pe');
    expect($page->get('suggestions'))->toBeEmpty();

    $page->set('login', 'peter')->call('invite')->assertHasErrors('login');
    expect(Buddy::count())->toBe(0);
});

test('the public profile toggle saves and a private buddy receives no workouts', function () {
    Livewire::actingAs($this->peter)->test(PublicProfile::class)
        ->assertSet('public', true)
        ->set('public', false)
        ->assertDispatched('profile-visibility-changed');
    expect($this->peter->fresh()->profile_public)->toBeFalse();

    makeBuddies($this->antoine, $this->peter);
    $workout = Workout::factory()->for($this->antoine)->create();

    expect(app(ShareWorkout::class)->handle($this->antoine, $workout, collect([$this->peter->fresh()])))->toBe(0)
        ->and(SharedWorkout::count())->toBe(0);

    Livewire::actingAs($this->antoine)->test(Show::class, ['workout' => $workout])
        ->assertSet('shareTo', [])
        ->tap(fn ($c) => expect($c->get('buddies'))->toBeEmpty());

    $this->actingAs($this->peter)->get(route('profile.edit'))->assertOk()->assertSee(__('Public profile'));
});
