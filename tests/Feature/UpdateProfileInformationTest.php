<?php

use App\Livewire\Profile\UpdateProfileInformation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('profile information is updated and the page is reloaded', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UpdateProfileInformation::class)
        ->set('name', 'Jan Jansen')
        ->set('username', 'janjansen')
        ->set('email', $user->email)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())
        ->name->toBe('Jan Jansen')
        ->username->toBe('janjansen');
    expect(session('status'))->toBe('Profiel bijgewerkt.');
});

test('the page is not reloaded when validation fails', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UpdateProfileInformation::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name')
        ->assertNoRedirect();
});
