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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('standard body parts show their picture', function () {
    Storage::fake('public');
    Storage::disk('public')->put('bodyparts/chest.png', 'png');
    Storage::disk('public')->put('bodyparts/full-body.png', 'png');
    $this->seed(BodyPartSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get(route('body-parts.index'))
        ->assertOk()
        ->assertSee('/storage/bodyparts/chest.png')
        ->assertSee('/storage/bodyparts/full-body.png');
});

test('own body parts have no picture', function () {
    Storage::fake('public');
    Storage::disk('public')->put('bodyparts/chest.png', 'png');
    $part = BodyPart::factory()->for(User::factory())->create(['slug' => 'chest']);

    expect($part->imageUrl())->toBeNull();
});

test('standard muscle groups show their picture', function () {
    Storage::fake('public');
    Storage::disk('public')->put('musclegroups/chest.png', 'png');
    $this->seed(BodyPartSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get(route('muscle-groups.index'))
        ->assertOk()
        ->assertSee('/storage/musclegroups/chest.png');

    expect(MuscleGroup::factory()->for(User::factory())->create(['slug' => 'chest'])->imageUrl())->toBeNull();
});

test('users can upload a picture for their own body part', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(BodyPartsIndex::class)
        ->call('create')
        ->set('name', 'Nek')
        ->set('icon', 'fiber')
        ->set('image', UploadedFile::fake()->image('nek.png', 300, 300))
        ->call('save')
        ->assertHasNoErrors();

    $part = BodyPart::sole();

    expect($part->icon)->toBe('fiber')
        ->and($part->image_path)->toStartWith('bodyparts/')
        ->and($part->imageUrl())->toContain($part->image_path);
    Storage::disk('public')->assertExists($part->image_path);
});

test('replacing or removing a picture deletes the old file', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $group = MuscleGroup::factory()->for($user)->for(BodyPart::factory()->for($user))->create();

    $component = Livewire::actingAs($user)->test(MuscleGroupsIndex::class)
        ->call('edit', $group->id)
        ->set('image', UploadedFile::fake()->image('eerste.png'))
        ->call('save')
        ->assertHasNoErrors();
    $first = $group->fresh()->image_path;

    $component->call('edit', $group->id)
        ->assertSet('currentImage', Storage::disk('public')->url($first))
        ->set('image', UploadedFile::fake()->image('tweede.png'))
        ->call('save');
    $second = $group->fresh()->image_path;

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    $component->call('edit', $group->id)->call('deleteImage')->call('save');

    expect($group->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

test('deleting a body part also deletes its picture', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Storage::disk('public')->put('bodyparts/eigen.png', 'png');
    $part = BodyPart::factory()->for($user)->create(['image_path' => 'bodyparts/eigen.png']);

    Livewire::actingAs($user)->test(BodyPartsIndex::class)->call('delete', $part->id);

    Storage::disk('public')->assertMissing('bodyparts/eigen.png');
});

test('only images are accepted as picture', function () {
    Storage::fake('public');

    Livewire::actingAs(User::factory()->create())->test(BodyPartsIndex::class)
        ->call('create')
        ->set('name', 'Nek')
        ->set('image', UploadedFile::fake()->create('nek.pdf', 100, 'application/pdf'))
        ->assertHasErrors(['image'])
        ->assertSet('image', null);
});
