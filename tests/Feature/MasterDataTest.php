<?php

use App\Livewire\BodyParts\Index as BodyPartsIndex;
use App\Livewire\MuscleGroups\Index as MuscleGroupsIndex;
use App\Livewire\Muscles\Index as MusclesIndex;
use App\Models\BodyPart;
use App\Models\Muscle;
use App\Models\MuscleGroup;
use App\Models\User;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the seeder creates the standard list and can run twice', function () {
    $this->seed(BodyPartSeeder::class);
    $this->seed(BodyPartSeeder::class);

    expect(BodyPart::count())->toBe(9)
        ->and(MuscleGroup::count())->toBe(28)
        ->and(Muscle::count())->toBe(46)
        ->and(BodyPart::whereNotNull('user_id')->exists())->toBeFalse();
});

test('the master data pages render for verified users', function (string $route) {
    $this->seed(BodyPartSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertOk()
        ->assertSee('Chest');
})->with(['body-parts.index', 'muscle-groups.index', 'muscles.index']);

test('guests are redirected to the login page', function () {
    $this->get(route('body-parts.index'))->assertRedirect(route('login'));
});

test('users can add their own body part', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(BodyPartsIndex::class)
        ->call('create')
        ->set('name', 'Nek')
        ->call('save')
        ->assertHasNoErrors();

    expect(BodyPart::sole())
        ->name->toBe('Nek')
        ->slug->toBe('nek')
        ->user_id->toBe($user->id);
});

test('standard items cannot be edited or deleted', function () {
    $this->seed(BodyPartSeeder::class);
    $chest = MuscleGroup::where('slug', 'chest')->sole();

    Livewire::actingAs(User::factory()->create())->test(MuscleGroupsIndex::class)
        ->call('edit', $chest->id)
        ->assertForbidden();

    Livewire::actingAs(User::factory()->create())->test(MuscleGroupsIndex::class)
        ->call('delete', $chest->id)
        ->assertForbidden();

    expect($chest->fresh())->not->toBeNull();
});

test('users only see their own items next to the standard list', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $part = BodyPart::factory()->for($owner)->create(['name' => 'Nek']);

    expect(BodyPart::visibleTo($owner)->pluck('id'))->toContain($part->id)
        ->and(BodyPart::visibleTo($other)->pluck('id'))->not->toContain($part->id);

    Livewire::actingAs($other)->test(BodyPartsIndex::class)
        ->call('edit', $part->id)
        ->assertNotFound();
});

test('a body part with muscle groups cannot be deleted', function () {
    $user = User::factory()->create();
    $part = BodyPart::factory()->for($user)->create();
    MuscleGroup::factory()->for($user)->for($part)->create();

    Livewire::actingAs($user)->test(BodyPartsIndex::class)
        ->call('delete', $part->id)
        ->assertSet('error', fn ($error) => str_contains($error, 'spiergroep'));

    expect($part->fresh())->not->toBeNull();
});

test('users can add a muscle to a standard muscle group', function () {
    $this->seed(BodyPartSeeder::class);
    $user = User::factory()->create();
    $chest = MuscleGroup::where('slug', 'chest')->sole();

    Livewire::actingAs($user)->test(MusclesIndex::class)
        ->call('create')
        ->set('muscle_group_id', $chest->id)
        ->set('name', 'Subclavius')
        ->set('latin_name', 'Musculus subclavius')
        ->call('save')
        ->assertHasNoErrors();

    expect(Muscle::where('user_id', $user->id)->sole())
        ->muscle_group_id->toBe($chest->id)
        ->latin_name->toBe('Musculus subclavius');
});

test('muscle group colors must be hex codes', function () {
    $this->seed(BodyPartSeeder::class);

    Livewire::actingAs(User::factory()->create())->test(MuscleGroupsIndex::class)
        ->call('create')
        ->set('body_part_id', BodyPart::first()->id)
        ->set('name', 'Grip')
        ->set('color', 'rood')
        ->call('save')
        ->assertHasErrors(['color']);
});
