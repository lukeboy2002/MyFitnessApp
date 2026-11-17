<?php

use App\Actions\DeleteUserAccount;
use App\Livewire\Equipment\Index;
use App\Livewire\Exercises\Form;
use App\Livewire\Exercises\Index as ExercisesIndex;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create(['locale' => 'nl']);
});

test('the migration created the standard list from the old fixed list', function () {
    expect(Schema::hasColumn('exercises', 'equipment_id'))->toBeTrue()
        ->and(Schema::hasColumn('exercises', 'equipment'))->toBeFalse()
        ->and(Equipment::whereNull('user_id')->orderBy('sort_order')->pluck('slug')->all())->toBe([
            'none', 'machine', 'cable', 'plate', 'barbell', 'dumbbell', 'kettlebell', 'band', 'suspension', 'cardio_machine', 'other',
        ]);
});

test('the page shows the standard list translated, and own equipment only to its owner', function () {
    Equipment::create(['user_id' => User::factory()->create()->id, 'name' => 'Iemand anders', 'slug' => 'iemand-anders']);

    $this->actingAs($this->user)->get(route('equipment.index'))
        ->assertOk()
        ->assertSee('Materiaal')
        ->assertSee('Weerstandsband')
        ->assertDontSee('Iemand anders');
});

test('a user can add, edit and delete own equipment', function () {
    $component = Livewire::actingAs($this->user)->test(Index::class)
        ->call('create')
        ->set('name', 'Smith machine')
        ->call('save')
        ->assertHasNoErrors();

    $own = Equipment::where('user_id', $this->user->id)->sole();
    expect($own->name)->toBe('Smith machine')->and($own->slug)->toBe('smith-machine');

    $component->call('edit', $own->id)->set('name', 'Smith rack')->call('save');
    expect($own->fresh()->name)->toBe('Smith rack');

    $component->call('delete', $own->id);
    expect(Equipment::find($own->id))->toBeNull();
});

test('standard equipment can only be changed by an admin, and keeps its slug and translation', function () {
    $band = Equipment::where('slug', 'band')->whereNull('user_id')->sole();

    Livewire::actingAs($this->user)->test(Index::class)->call('edit', $band->id)->assertForbidden();

    $admin = User::factory()->create(['is_admin' => true, 'locale' => 'nl']);
    Livewire::actingAs($admin)->test(Index::class)
        ->call('edit', $band->id)
        ->assertSet('name', 'Weerstandsband')
        ->call('save');
    expect($band->fresh()->name)->toBe('Resistance band');   // ongewijzigd: blijft vertaald

    Livewire::actingAs($admin)->test(Index::class)->call('edit', $band->id)->set('name', 'Elastiek')->call('save');
    expect($band->fresh())->name->toBe('Elastiek')->slug->toBe('band');
});

test('equipment that is still used cannot be deleted', function () {
    $own = Equipment::create(['user_id' => $this->user->id, 'name' => 'Smith machine', 'slug' => 'smith-machine']);
    Exercise::factory()->create(['user_id' => $this->user->id, 'equipment_id' => $own->id]);

    Livewire::actingAs($this->user)->test(Index::class)
        ->call('delete', $own->id)
        ->assertSee('Smith machine wordt nog gebruikt bij 1 oefening');

    expect($own->fresh())->not->toBeNull();
});

test('own equipment can be chosen for an own exercise and used as filter', function () {
    $own = Equipment::create(['user_id' => $this->user->id, 'name' => 'Smith machine', 'slug' => 'smith-machine']);
    $chest = MuscleGroup::whereNull('user_id')->where('slug', 'chest')->value('id');

    Livewire::actingAs($this->user)->test(Form::class)
        ->assertSee('Smith machine')
        ->set('name', 'Smith Bench Press')
        ->set('equipment_id', $own->id)
        ->set('primary_muscle_group_id', $chest)
        ->call('save')
        ->assertHasNoErrors();

    $exercise = Exercise::where('name', 'Smith Bench Press')->sole();
    expect($exercise->equipment->is($own))->toBeTrue();

    Livewire::actingAs($this->user)->test(ExercisesIndex::class)
        ->set('equipmentFilter', $own->id)
        ->assertSee('Smith Bench Press');
});

test('equipment of someone else cannot be chosen', function () {
    $foreign = Equipment::create(['user_id' => User::factory()->create()->id, 'name' => 'Geheim', 'slug' => 'geheim']);

    Livewire::actingAs($this->user)->test(Form::class)
        ->assertDontSee('Geheim')
        ->set('name', 'Test')
        ->set('equipment_id', $foreign->id)
        ->set('primary_muscle_group_id', MuscleGroup::whereNull('user_id')->value('id'))
        ->call('save')
        ->assertHasErrors('equipment_id');
});

test('a standard exercise made by an admin only offers standard equipment', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Equipment::create(['user_id' => $admin->id, 'name' => 'Mijn eigen ding', 'slug' => 'mijn-eigen-ding']);

    Livewire::actingAs($admin)->withQueryParams(['standard' => 1])->test(Form::class)
        ->assertDontSee('Mijn eigen ding')
        ->assertSee('Barbell');
});

test('deleting an account removes own equipment', function () {
    Equipment::create(['user_id' => $this->user->id, 'name' => 'Smith machine', 'slug' => 'smith-machine']);

    app(DeleteUserAccount::class)->handle($this->user);

    expect(Equipment::where('name', 'Smith machine')->exists())->toBeFalse();
});
