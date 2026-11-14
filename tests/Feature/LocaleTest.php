<?php

use App\Models\User;
use App\Models\WorkoutExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests get the language of their browser', function () {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get(route('login'))
        ->assertOk()
        ->assertSee('lang="de"', false)
        ->assertSee('Passwort vergessen?');

    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')->get(route('login'))
        ->assertSee('Forgot your password?');

    // Een taal die de app niet kent: de standaardtaal
    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->get(route('login'))
        ->assertSee('Wachtwoord vergeten?');
});

test('a guest can pick a language on the login page', function () {
    $this->from(route('login'))->post(route('locale.update', 'en'))
        ->assertRedirect(route('login'));

    $this->get(route('login'))->assertSee('Forgot your password?');

    $this->post(route('locale.update', 'fr'))->assertNotFound();
});

test('your own language wins over the browser and is saved in your profile', function () {
    $user = User::factory()->create(['locale' => 'de']);

    $this->actingAs($user)->withHeader('Accept-Language', 'en')->get(route('profile.edit'))
        ->assertSee('Persönliche Daten')
        ->assertSee('Abmelden');

    $this->from(route('profile.edit'))->post(route('locale.update', 'en'))->assertRedirect(route('profile.edit'));

    expect($user->fresh()->locale)->toBe('en')
        ->and($user->fresh()->preferredLocale())->toBe('en');

    $this->get(route('profile.edit'))->assertSee('Personal details')->assertSee('Log out');
});

test('a new account keeps the language it registered in', function () {
    $this->withHeader('Accept-Language', 'de')->post(route('register'), [
        'name' => 'Anna Schmidt',
        'username' => 'anna',
        'email' => 'anna@example.com',
        'password' => 'geheim-wachtwoord-123',
        'password_confirmation' => 'geheim-wachtwoord-123',
    ]);

    expect(User::where('username', 'anna')->value('locale'))->toBe('de');
});

test('numbers use a decimal comma in Dutch and German and a point in English', function () {
    app()->setLocale('nl');
    expect(WorkoutExercise::number(82.5))->toBe('82,5');

    app()->setLocale('de');
    expect(WorkoutExercise::number(82.5))->toBe('82,5');

    app()->setLocale('en');
    expect(WorkoutExercise::number(82.5))->toBe('82.5');
});

test('validation messages are translated', function () {
    $this->withHeader('Accept-Language', 'de')->from(route('login'))
        ->post(route('login'), ['login' => '', 'password' => ''])
        ->assertSessionHasErrors(['login' => 'Anmeldung muss ausgefüllt werden.']);
});
