<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users can register with a username', function () {
    $this->post(route('register'), [
        'name' => 'Jan Jansen',
        'username' => 'janjansen',
        'email' => 'Jan@Example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::sole();

    expect($user->username)->toBe('janjansen')
        ->and($user->email)->toBe('jan@example.com')
        ->and($user->profile_photo_path)->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('registration requires a unique username', function () {
    User::factory()->create(['username' => 'janjansen']);

    $this->post(route('register'), [
        'name' => 'Jan Jansen',
        'username' => 'janjansen',
        'email' => 'jan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('users can log in with their email address', function () {
    $user = User::factory()->create(['email' => 'jan@example.com']);

    $this->post(route('login'), ['login' => 'jan@example.com', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('users can log in with their username', function () {
    $user = User::factory()->create(['username' => 'janjansen']);

    $this->post(route('login'), ['login' => 'janjansen', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('users cannot log in with an invalid password', function () {
    User::factory()->create(['username' => 'janjansen']);

    $this->post(route('login'), ['login' => 'janjansen', 'password' => 'wrong-password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});
