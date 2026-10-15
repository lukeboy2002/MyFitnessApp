<?php

use App\Livewire\Profile\TwoFactorAuthentication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('users can confirm their password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('password.confirm'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('auth.password_confirmed_at');
});

test('the password is not confirmed with an invalid password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('password.confirm'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password')
        ->assertSessionMissing('auth.password_confirmed_at');
});

test('users are asked to confirm their password before enabling two factor authentication', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(TwoFactorAuthentication::class)
        ->call('enable')
        ->assertSet('confirmingPassword', true)
        ->assertSet('showingQrCode', false)
        ->assertNoRedirect();

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

test('enabling two factor authentication continues after confirming the password', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(TwoFactorAuthentication::class)
        ->call('enable')
        ->set('confirmablePassword', 'password')
        ->call('confirmPassword')
        ->assertHasNoErrors()
        ->assertSet('confirmingPassword', false)
        ->assertSet('showingQrCode', true)
        ->assertSee('<svg', escape: false);

    expect($user->fresh()->two_factor_secret)->not->toBeNull()
        ->and(session('auth.password_confirmed_at'))->not->toBeNull();
});

test('two factor authentication is not enabled with an invalid password', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(TwoFactorAuthentication::class)
        ->call('enable')
        ->set('confirmablePassword', 'wrong-password')
        ->call('confirmPassword')
        ->assertHasErrors('confirmablePassword')
        ->assertSet('confirmingPassword', true)
        ->assertSet('showingQrCode', false);

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

test('users can enable two factor authentication after confirming their password', function () {
    $user = User::factory()->create(['email' => 'jan@example.com']);

    session(['auth.password_confirmed_at' => time()]);

    Livewire::actingAs($user)
        ->test(TwoFactorAuthentication::class)
        ->call('enable')
        ->assertSet('showingQrCode', true)
        ->assertSee('<svg', escape: false);

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->twoFactorQrCodeUrl())->toContain('jan%40example.com');
});
