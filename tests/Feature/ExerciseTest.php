<?php

use App\Enums\ExerciseType;
use App\Livewire\Exercises\Form;
use App\Livewire\Exercises\Index;
use App\Livewire\MuscleGroups\Index as MuscleGroupsIndex;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use Database\Seeders\BodyPartSeeder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create();
});

function standardGroup(string $slug): MuscleGroup
{
    return MuscleGroup::whereNull('user_id')->where('slug', $slug)->firstOrFail();
}

test('the seeder creates the standard exercises with exactly one primary muscle group', function () {
    $this->seed(ExerciseSeeder::class);
    $this->seed(ExerciseSeeder::class); // nog een keer: niets dubbel

    expect(Exercise::whereNull('user_id')->count())->toBe(52 + count(require database_path('seeders/data/exercise-library.php')));

    Exercise::with('muscleGroups')->get()->each(function (Exercise $exercise) {
        expect($exercise->muscleGroups->where('pivot.is_primary', true))->toHaveCount(1);
    });

    $seatedRow = Exercise::where('slug', 'seated-row')->first();
    expect($seatedRow->primaryMuscleGroup()->slug)->toBe('upper-back')
        ->and($seatedRow->secondaryMuscleGroups()->pluck('slug')->all())->toContain('lats', 'biceps')
        ->and($seatedRow->instructions)->toBeArray()->not->toBeEmpty();

    expect(Exercise::where('slug', 'treadmill')->first()->type)->toBe(ExerciseType::Cardio)
        ->and(Exercise::where('slug', 'bench-press-barbell')->first()->primaryMuscleGroup()->slug)->toBe('chest')
        ->and(Exercise::where('slug', 'pull-up-assisted')->first()->type)->toBe(ExerciseType::Bodyweight);
});

test('the index lists exercises and filters by type, muscle group and search', function () {
    $this->seed(ExerciseSeeder::class);

    $this->actingAs($this->user)->get(route('exercises.index'))->assertOk()->assertSee('Pec Fly Machine');

    Livewire::actingAs($this->user)->test(Index::class)
        ->set('typeFilter', 'cardio')
        ->assertSee('Treadmill')->assertDontSee('Pec Fly Machine')
        ->set('typeFilter', '')
        ->set('groupFilter', standardGroup('biceps')->id)
        ->assertSee('Seated Row')->assertDontSee('Treadmill')
        ->set('groupFilter', null)
        ->set('search', 'leg press')
        ->assertSee('Leg Press Machine')->assertDontSee('Seated Row');
});

test('guests are redirected to login', function () {
    $this->get(route('exercises.index'))->assertRedirect(route('login'));
});

test('a user can create an exercise with primary and secondary muscle groups', function () {
    Livewire::actingAs($this->user)->test(Form::class)
        ->set('name', 'Bench Press')
        ->set('type', 'strength')
        ->set('equipment', 'barbell')
        ->set('primary_muscle_group_id', standardGroup('chest')->id)
        ->set('secondary_muscle_group_ids', [standardGroup('triceps')->id, standardGroup('chest')->id])
        ->set('default_weight', '40')
        ->set('instructions', "Ga liggen\n\n  Druk omhoog  ")
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('exercises.show', Exercise::first()));

    $exercise = Exercise::first();
    expect($exercise->user_id)->toBe($this->user->id)
        ->and($exercise->primaryMuscleGroup()->slug)->toBe('chest')
        ->and($exercise->secondaryMuscleGroups()->pluck('slug')->all())->toBe(['triceps'])
        ->and($exercise->instructions)->toBe(['Ga liggen', 'Druk omhoog'])
        ->and($exercise->defaultTarget())->toBe('3 × 10 · 40 kg');

    $this->actingAs($this->user)->get(route('exercises.show', $exercise))->assertOk()->assertSee('Druk omhoog');
});

test('a primary muscle group is required', function () {
    Livewire::actingAs($this->user)->test(Form::class)
        ->set('name', 'Iets')
        ->call('save')
        ->assertHasErrors(['primary_muscle_group_id' => 'required']);
});

test('only the target fields of the type are stored', function () {
    Livewire::actingAs($this->user)->test(Form::class)
        ->set('name', 'Roeien')
        ->set('type', 'cardio')
        ->assertSet('primary_muscle_group_id', standardGroup('cardio')->id)
        ->assertSet('default_minutes', 20)
        ->set('default_intensity', 8)
        ->call('save')
        ->assertHasNoErrors();

    $exercise = Exercise::first();
    expect($exercise->default_sets)->toBeNull()
        ->and($exercise->default_reps)->toBeNull()
        ->and($exercise->default_duration_seconds)->toBe(1200)
        ->and($exercise->defaultTarget())->toBe('20 min · niveau 8');
});

test('a bodyweight exercise can have assisted (negative) extra weight', function () {
    Livewire::actingAs($this->user)->test(Form::class)
        ->set('name', 'Pull Up (Assisted)')
        ->set('type', 'bodyweight')
        ->set('primary_muscle_group_id', standardGroup('lats')->id)
        ->set('default_weight', '-20')
        ->call('save')
        ->assertHasNoErrors();

    expect(Exercise::first()->defaultTarget())->toBe('3 × 10 · -20 kg');
});

test('standard exercises and exercises of others cannot be changed', function () {
    $standard = Exercise::factory()->create();
    $other = Exercise::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($this->user)->get(route('exercises.show', $standard))->assertOk();
    $this->actingAs($this->user)->get(route('exercises.edit', $standard))->assertForbidden();
    $this->actingAs($this->user)->get(route('exercises.show', $other))->assertForbidden();

    Livewire::actingAs($this->user)->test(Index::class)->call('delete', $standard->id)->assertForbidden();
    expect($standard->fresh())->not->toBeNull();
});

test('without an own image the primary muscle group image is used', function () {
    Storage::disk('public')->put('musclegroups/chest.png', 'png');
    $exercise = Exercise::factory()->create();
    $exercise->syncMuscleGroups(standardGroup('chest')->id);

    expect($exercise->imageUrl())->toEndWith('/storage/musclegroups/chest.png');
});

test('an image can be uploaded and replaced and the old file is deleted', function () {
    $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
    $exercise->syncMuscleGroups(standardGroup('chest')->id);

    Livewire::actingAs($this->user)->test(Form::class, ['exercise' => $exercise])
        ->set('image', UploadedFile::fake()->image('a.jpg'))
        ->call('save')->assertHasNoErrors();

    $first = $exercise->fresh()->image_path;
    expect($first)->toStartWith('exercises/');
    Storage::disk('public')->assertExists($first);

    Livewire::actingAs($this->user)->test(Form::class, ['exercise' => $exercise->fresh()])
        ->set('image', UploadedFile::fake()->image('b.png'))
        ->call('save')->assertHasNoErrors();

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($exercise->fresh()->image_path);
});

test('copying a standard exercise creates an own exercise with the same muscle groups', function () {
    $this->seed(ExerciseSeeder::class);
    $standard = Exercise::where('slug', 'seated-row')->first();

    Livewire::actingAs($this->user)->test(Index::class)
        ->call('duplicate', $standard->id)
        ->assertRedirect();

    $copy = Exercise::where('user_id', $this->user->id)->first();
    expect($copy->name)->toBe('Seated Row')
        ->and($copy->primaryMuscleGroup()->slug)->toBe('upper-back')
        ->and($copy->muscleGroups)->toHaveCount($standard->muscleGroups->count())
        ->and($copy->instructions)->toBe($standard->instructions);
});

test('archiving hides an exercise and restoring brings it back', function () {
    $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'name' => 'Mijn oefening']);

    $link = route('exercises.show', $exercise);

    Livewire::actingAs($this->user)->test(Index::class)
        ->call('archive', $exercise->id)
        ->assertDontSeeHtml($link)
        ->set('showArchived', true)
        ->assertSeeHtml($link)
        ->call('restore', $exercise->id)
        ->set('showArchived', false)
        ->assertSeeHtml($link);
});

test('deleting an exercise also deletes its image', function () {
    Storage::disk('public')->put('exercises/x.jpg', 'jpg');
    $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'image_path' => 'exercises/x.jpg']);

    Livewire::actingAs($this->user)->test(Index::class)->call('delete', $exercise->id);

    expect($exercise->fresh())->toBeNull();
    Storage::disk('public')->assertMissing('exercises/x.jpg');
});

test('a muscle group with exercises cannot be deleted', function () {
    $group = MuscleGroup::factory()->create(['user_id' => $this->user->id, 'body_part_id' => standardGroup('chest')->body_part_id]);
    $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
    $exercise->syncMuscleGroups($group->id);

    Livewire::actingAs($this->user)->test(MuscleGroupsIndex::class)
        ->call('delete', $group->id)
        ->assertSet('error', fn ($error) => str_contains($error, 'oefening'));

    expect($group->fresh())->not->toBeNull();
});

test('deleting asks for confirmation in a modal first', function () {
    $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'name' => 'Mijn Curl']);

    $component = Livewire::actingAs($this->user)->test(Index::class)
        ->call('confirmDelete', $exercise->id)
        ->assertSet('confirmingDeletion', true)
        ->assertSet('deletingName', 'Mijn Curl')
        ->assertSee('Weet je zeker dat je');

    expect($exercise->fresh())->not->toBeNull();

    $component->call('cancelDelete')->assertSet('confirmingDeletion', false)->assertSet('deletingId', null);
    expect($exercise->fresh())->not->toBeNull();

    $component->call('confirmDelete', $exercise->id)->call('deleteConfirmed')->assertSet('confirmingDeletion', false);
    expect($exercise->fresh())->toBeNull();
});

test('standard items cannot be offered for deletion', function () {
    $standard = Exercise::factory()->create(['user_id' => null]);

    Livewire::actingAs($this->user)->test(Index::class)->call('confirmDelete', $standard->id)->assertForbidden();
    Livewire::actingAs($this->user)->test(MuscleGroupsIndex::class)->call('confirmDelete', standardGroup('chest')->id)->assertForbidden();
});

test('confirming a muscle group deletion still checks for linked exercises', function () {
    $group = MuscleGroup::factory()->create(['user_id' => $this->user->id, 'body_part_id' => standardGroup('chest')->body_part_id]);
    Exercise::factory()->create(['user_id' => $this->user->id])->syncMuscleGroups($group->id);

    Livewire::actingAs($this->user)->test(MuscleGroupsIndex::class)
        ->call('confirmDelete', $group->id)
        ->call('deleteConfirmed')
        ->assertSet('error', fn ($error) => str_contains($error, 'oefening'));

    expect($group->fresh())->not->toBeNull();
});
