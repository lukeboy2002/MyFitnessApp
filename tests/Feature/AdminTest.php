<?php

use App\Livewire\Admin\Users;
use App\Livewire\MuscleGroups\Index as MuscleGroupsIndex;
use App\Models\MuscleGroup;
use App\Models\User;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('an account can be made admin from the command line', function () {
    $user = User::factory()->create(['email' => 'antoine@example.test']);

    $this->artisan('fitness:admin', ['email' => 'antoine@example.test'])->assertSuccessful();
    expect($user->fresh()->isAdmin())->toBeTrue();

    $this->artisan('fitness:admin', ['email' => 'antoine@example.test', '--remove' => true])->assertSuccessful();
    expect($user->fresh()->isAdmin())->toBeFalse();

    $this->artisan('fitness:admin', ['email' => 'niemand@example.test'])->assertFailed();
});

test('only admins see the users page', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users'))->assertForbidden();

    $admin = User::factory()->create(['name' => 'Beheerder']);
    $admin->forceFill(['is_admin' => true])->save();
    User::factory()->create(['name' => 'Gewone Gebruiker']);

    $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee(['Beheerder', 'Gewone Gebruiker', 'Admin maken']);
});

test('an admin can give and take admin rights, but not from themselves', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $other = User::factory()->create();

    Livewire::actingAs($admin)->test(Users::class)->call('toggleAdmin', $other->id);
    expect($other->fresh()->isAdmin())->toBeTrue();

    Livewire::actingAs($admin)->test(Users::class)->call('toggleAdmin', $admin->id)->assertForbidden();
    expect($admin->fresh()->isAdmin())->toBeTrue();
});

test('an admin can edit the standard list and keeps its slug', function () {
    $this->seed(BodyPartSeeder::class);
    $chest = MuscleGroup::whereNull('user_id')->where('slug', 'chest')->sole();
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    Livewire::actingAs($admin)->test(MuscleGroupsIndex::class)
        ->call('edit', $chest->id)
        ->set('name', 'Borst')
        ->call('save')
        ->assertHasNoErrors();

    expect($chest->fresh())->name->toBe('Borst')->slug->toBe('chest')->user_id->toBeNull();

    // Eigen items van anderen blijven privé
    $own = MuscleGroup::factory()->create(['user_id' => User::factory()->create()->id]);
    Livewire::actingAs($admin)->test(MuscleGroupsIndex::class)->call('edit', $own->id)->assertNotFound();
});
