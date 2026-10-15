<?php

use App\Livewire\Profile\Passkeys;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('users are asked to confirm their password before adding a passkey', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Passkeys::class)
        ->set('passkeyName', 'Mijn Mac')
        ->call('startRegistration')
        ->assertSet('confirmingPassword', true)
        ->assertNotDispatched('passkey-register');
});

test('adding a passkey continues after confirming the password', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Passkeys::class)
        ->set('passkeyName', 'Mijn Mac')
        ->call('startRegistration')
        ->set('confirmablePassword', 'password')
        ->call('confirmPassword')
        ->assertHasNoErrors()
        ->assertSet('confirmingPassword', false)
        ->assertDispatched('passkey-register', name: 'Mijn Mac');
});

test('a passkey is not added with an invalid password', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Passkeys::class)
        ->set('passkeyName', 'Mijn Mac')
        ->call('startRegistration')
        ->set('confirmablePassword', 'wrong-password')
        ->call('confirmPassword')
        ->assertHasErrors('confirmablePassword')
        ->assertNotDispatched('passkey-register');
});

test('the pending action cannot be changed by the client', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Passkeys::class)
        ->set('pendingPasswordConfirmation', ['delete', ['id' => 1]]);
})->throws(CannotUpdateLockedPropertyException::class);
