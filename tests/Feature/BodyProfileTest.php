<?php

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Livewire\Profile\UpdatePersonalDetails;
use App\Livewire\Profile\WeightLog;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the profile page shows the personal details and weight cards', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Persoonlijke gegevens')
        ->assertSee('Weging opslaan');
});

test('personal details are saved and give age, bmi and max heart rate', function () {
    $this->travelTo(now()->setDate(2026, 10, 26));
    $user = User::factory()->create();
    $user->bodyWeights()->create(['measured_on' => '2026-10-20', 'weight_kg' => 81]);

    Livewire::actingAs($user)->test(UpdatePersonalDetails::class)
        ->set('birthDate', '1976-05-01')
        ->set('gender', 'male')
        ->set('heightCm', '180')
        ->set('targetWeight', '78,5')
        ->set('goal', 'lose_weight')
        ->set('experience', 'advanced')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('50 jaar')
        ->assertSee('25')
        ->assertSee('173 bpm');

    $user->refresh();
    expect($user->gender)->toBe(Gender::Male)
        ->and($user->goal)->toBe(FitnessGoal::LoseWeight)
        ->and($user->height_cm)->toBe(180)
        ->and((float) $user->target_weight_kg)->toBe(78.5)
        ->and($user->age())->toBe(50)
        ->and($user->bmi())->toBe(25.0)
        ->and($user->maxHeartRate())->toBe(173);
});

test('personal details are validated and can be cleared', function () {
    $user = User::factory()->create(['height_cm' => 180, 'goal' => 'strength']);

    Livewire::actingAs($user)->test(UpdatePersonalDetails::class)
        ->set('heightCm', '20')
        ->set('birthDate', now()->addDay()->format('Y-m-d'))
        ->set('gender', 'robot')
        ->call('save')
        ->assertHasErrors(['heightCm', 'birthDate', 'gender'])
        ->set('heightCm', '')
        ->set('birthDate', '')
        ->set('gender', '')
        ->set('goal', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->refresh()->height_cm)->toBeNull()
        ->and($user->goal)->toBeNull();
});

test('weighing twice on one day replaces the weight', function () {
    $user = User::factory()->create(['target_weight_kg' => 80]);

    Livewire::actingAs($user)->test(WeightLog::class)
        ->set('weight', '84,2')
        ->set('bodyFat', '19,5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('body-weight-saved')
        ->set('weight', '84')
        ->call('save')
        ->assertSee('84 kg')
        ->assertSee('Nog');

    expect($user->bodyWeights()->count())->toBe(1)
        ->and((float) $user->bodyWeights()->first()->weight_kg)->toBe(84.0)
        ->and($user->bodyWeights()->first()->body_fat)->toBeNull();
});

test('weights are validated and only your own can be deleted', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = $user->bodyWeights()->create(['measured_on' => today(), 'weight_kg' => 80]);
    $theirs = $other->bodyWeights()->create(['measured_on' => today(), 'weight_kg' => 70]);

    $component = Livewire::actingAs($user)->test(WeightLog::class)
        ->set('weight', '5')
        ->set('date', now()->addDay()->format('Y-m-d'))
        ->call('save')
        ->assertHasErrors(['weight', 'date'])
        ->call('delete', $mine->id);

    expect($mine->fresh())->toBeNull();

    try {
        $component->call('delete', $theirs->id);
    } catch (ModelNotFoundException) {
    }

    expect($theirs->fresh())->not->toBeNull();
});
