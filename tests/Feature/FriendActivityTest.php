<?php

use App\Actions\ShareTraining;
use App\Actions\StartWorkoutSession;
use App\Livewire\Buddies\Index;
use App\Livewire\Sessions\Summary;
use App\Models\Buddy;
use App\Models\Exercise;
use App\Models\SessionLike;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Support\FriendActivity;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->antoine = User::factory()->create(['name' => 'Antoine', 'username' => 'antoine', 'profile_public' => true, 'locale' => 'nl']);
    $this->peter = User::factory()->create(['name' => 'Peter', 'username' => 'peter', 'profile_public' => true, 'locale' => 'nl']);
    $this->stranger = User::factory()->create(['name' => 'Kees', 'profile_public' => true]);
    Buddy::create(['user_id' => $this->antoine->id, 'buddy_id' => $this->peter->id, 'accepted_at' => now()]);
});

/** Afgeronde training van $user met 3 afgevinkte sets */
function finishedTraining(User $user, string $name = 'Armen met barbell'): WorkoutSession
{
    $workout = Workout::factory()->for($user)->create(['name' => $name]);
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => Exercise::factory()->create()->id, 'target_sets' => 3]);
    $session = app(StartWorkoutSession::class)->handle($user, $workout);
    $session->sets()->update(['is_completed' => true, 'reps' => 10, 'weight' => 20]);
    $session->finish();

    return $session->fresh();
}

test('a finished training is private until you share it, and friends get a notification once', function () {
    $session = finishedTraining($this->antoine);
    expect($session->isShared())->toBeFalse()
        ->and(FriendActivity::canSee($this->peter, $session))->toBeFalse();

    Livewire::actingAs($this->antoine)->test(Summary::class, ['session' => $session])
        ->assertSee(['Delen met vrienden', 'Laat je 1 vriend deze training zien.'])
        ->call('toggleShare')
        ->assertSee(['Gedeeld', 'Peter kregen een melding.']);

    $session->refresh();
    expect($session->isShared())->toBeTrue()
        ->and($session->shared_summary['sets'])->toBe(3)
        ->and($session->shared_summary['volume'])->toEqual(600)
        ->and(FriendActivity::canSee($this->peter, $session))->toBeTrue()
        ->and(FriendActivity::canSee($this->stranger, $session))->toBeFalse()
        ->and($this->peter->messages()->sole()->title)->toBe('Antoine heeft een training gedeeld');

    // Uit en weer aan: geen tweede melding
    Livewire::actingAs($this->antoine)->test(Summary::class, ['session' => $session])->call('toggleShare')->call('toggleShare');
    expect($this->peter->messages()->count())->toBe(1);
});

test('friends see shared trainings in the activity tab and can give a thumbs up', function () {
    $session = finishedTraining($this->antoine);
    app(ShareTraining::class)->share($session);

    Livewire::actingAs($this->peter)->test(Index::class, ['tab' => 'activiteit'])
        ->assertSee(['Antoine', 'Armen met barbell', '3 sets'])
        ->assertDontSee('0 duimen')
        ->call('like', $session->id)
        ->assertSee(['Duim gegeven', '1 duim']);

    expect(SessionLike::sole()->user_id)->toBe($this->peter->id)
        ->and($this->antoine->messages()->sole()->title)->toBe('Peter gaf je een duim');

    // Eigenaar ziet wie een duim gaf; terugnemen kan
    Livewire::actingAs($this->antoine)->test(Index::class, ['tab' => 'activiteit'])->assertSee(['Jij', '1 duim', 'van Peter']);
    Livewire::actingAs($this->peter)->test(Index::class, ['tab' => 'activiteit'])->call('like', $session->id);
    expect(SessionLike::count())->toBe(0);

    // Geen vriend, of je eigen training: geen duim
    Livewire::actingAs($this->stranger)->test(Index::class, ['tab' => 'activiteit'])->call('like', $session->id)->assertNotFound();
    Livewire::actingAs($this->antoine)->test(Index::class, ['tab' => 'activiteit'])->call('like', $session->id)->assertNotFound();
});

test('unfriending or a private profile hides shared trainings', function () {
    $session = finishedTraining($this->antoine);
    app(ShareTraining::class)->share($session);
    expect(FriendActivity::canSee($this->peter, $session))->toBeTrue();

    $this->antoine->forceFill(['profile_public' => false])->save();
    expect(FriendActivity::canSee($this->peter, $session))->toBeFalse();

    $this->antoine->forceFill(['profile_public' => true])->save();
    Buddy::query()->delete();
    expect(FriendActivity::canSee($this->peter, $session))->toBeFalse();

    // Zonder vrienden geen deelkaart op de samenvatting
    Livewire::actingAs($this->antoine)->test(Summary::class, ['session' => finishedTraining($this->antoine, 'Benen')])
        ->assertDontSee('Delen met vrienden');
});

test('the buddies page opens on friends when there are invitations, otherwise on activity', function () {
    Livewire::actingAs($this->antoine)->test(Index::class)->assertSet('tab', 'activiteit');
    Buddy::create(['user_id' => $this->stranger->id, 'buddy_id' => $this->antoine->id]);
    Livewire::actingAs($this->antoine)->test(Index::class)->assertSet('tab', 'vrienden');
});
