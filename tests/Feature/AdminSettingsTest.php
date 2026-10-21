<?php

use App\Actions\Fortify\CreateNewUser;
use App\Livewire\Admin\Settings;
use App\Models\Exercise;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Support\Offline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function settingsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

test('only admins see the settings page', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.settings'))->assertForbidden();

    $this->actingAs(settingsAdmin())->get(route('admin.settings'))
        ->assertOk()
        ->assertSee(['Registratie staat', 'Mededeling', 'Standaarden', 'Systeem', 'Afgeronde trainingen']);
});

test('an announcement is shown to everyone until it is cleared', function () {
    $admin = settingsAdmin();
    $user = User::factory()->create();

    Livewire::actingAs($admin)->test(Settings::class)
        ->set('announcement', 'Zondag onderhoud')
        ->set('announcementType', 'warning')
        ->call('saveAnnouncement')
        ->assertHasNoErrors();

    $this->actingAs($user)->get(route('dashboard'))->assertSee('Zondag onderhoud');

    Livewire::actingAs($admin)->test(Settings::class)
        ->assertSet('announcement', 'Zondag onderhoud')
        ->set('announcement', '')
        ->call('saveAnnouncement');

    $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Zondag onderhoud');
});

test('an admin sets the default rest time and weekly goal', function () {
    expect(WorkoutExercise::defaultRestSeconds())->toBe(WorkoutExercise::DEFAULT_REST_SECONDS);

    Livewire::actingAs(settingsAdmin())->test(Settings::class)
        ->set('restSeconds', '1000')
        ->call('saveDefaults')
        ->assertHasErrors('restSeconds')
        ->set('restSeconds', '60')
        ->set('weeklyGoalHours', '4,5')
        ->call('saveDefaults')
        ->assertHasNoErrors()
        ->assertSet('weeklyGoalHours', '4,5');

    $exercise = Exercise::factory()->create();
    expect(WorkoutExercise::defaultsFor($exercise)['rest_seconds'])->toBe($exercise->type->usesSets() ? 60 : null);

    $user = app(CreateNewUser::class)->create([
        'name' => 'Nieuw', 'username' => 'nieuw', 'email' => 'nieuw@example.test',
        'password' => 'Wachtwoord-123!', 'password_confirmation' => 'Wachtwoord-123!',
    ]);
    expect($user->fresh()->weekly_goal_minutes)->toBe(270);
});

test('an admin can clear the cache', function () {
    Setting::put('announcement', 'Oud');
    expect(Setting::get('announcement'))->toBe('Oud');

    // Direct in de database gewijzigd: de cache toont nog de oude waarde
    Setting::where('key', 'announcement')->update(['value' => 'Nieuw']);
    expect(Setting::get('announcement'))->toBe('Oud');

    Livewire::actingAs(settingsAdmin())->test(Settings::class)->call('clearCache')->assertDispatched('cache-cleared');
    expect(Setting::get('announcement'))->toBe('Nieuw');
});

test('only admins can change settings', function () {
    Livewire::actingAs(User::factory()->create())->test(Settings::class)->assertForbidden();
});

test('an admin can take the site offline; only admins can still use it', function () {
    $admin = settingsAdmin();
    $user = User::factory()->create();

    Livewire::actingAs($admin)->test(Settings::class)
        ->assertSee('De site staat online')
        ->set('offlineMessage', 'Tot morgen!')
        ->call('toggleOffline')
        ->assertSee('De site staat offline');

    expect(Offline::isOn())->toBeTrue();

    $this->actingAs($user)->get(route('dashboard'))->assertStatus(503)->assertSee('Tot morgen!');
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('De site staat offline');
    $this->actingAs($admin)->get('/dashboard')->assertRedirect('/');

    auth()->logout();
    $this->get(route('login'))->assertOk();
    $this->get('/')->assertStatus(503)->assertSee('Beheerder? Inloggen');

    Livewire::actingAs($admin)->test(Settings::class)->call('toggleOffline');
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Tot morgen!');
});
