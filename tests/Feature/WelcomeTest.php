<?php

use App\Enums\FitnessGoal;
use App\Livewire\Dashboard;
use App\Livewire\Welcome;
use App\Models\User;
use Database\Seeders\BodyPartSeeder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([BodyPartSeeder::class, ExerciseSeeder::class]);
});

test('new users see the welcome screen first', function () {
    $user = User::factory()->notOnboarded()->create();

    Livewire::actingAs($user)->test(Dashboard::class)->assertRedirect(route('welcome'));

    $this->actingAs($user)->get(route('welcome'))
        ->assertOk()
        ->assertSee('Waar train je voor?')
        ->assertSee('Fit en gezond');
});

test('the starter workout follows your goal and experience', function () {
    $user = User::factory()->notOnboarded()->create(['goal' => FitnessGoal::Strength, 'experience' => 'advanced']);

    Livewire::actingAs($user)->test(Welcome::class)
        ->assertSet('goal', 'strength')
        ->assertSee('Kracht basis')
        ->assertSee('5 × 5')
        ->set('experience', 'beginner')
        ->assertSee('4 × 5')
        ->set('goal', 'lose_weight')
        ->assertSee('Vetverbranding')
        ->assertSee('20 min');
});

test('creating the starter workout saves it with your choices', function () {
    $user = User::factory()->notOnboarded()->create();

    Livewire::actingAs($user)->test(Welcome::class)
        ->set('goal', 'build_muscle')
        ->set('experience', 'intermediate')
        ->call('create')
        ->assertRedirect();

    $user->refresh();
    $workout = $user->workouts()->sole();

    expect($user->onboarded_at)->not->toBeNull()
        ->and($user->goal)->toBe(FitnessGoal::BuildMuscle)
        ->and($workout->name)->toBe('Full body opbouw')
        ->and($workout->exercises)->toHaveCount(7)
        ->and($workout->exercises->first()->target_sets)->toBe(4)
        ->and($workout->exercises->first()->rest_seconds)->toBe(90)
        ->and($workout->muscleGroups)->not->toBeEmpty();

    Livewire::actingAs($user)->test(Dashboard::class)->assertNoRedirect();
});

test('skipping only remembers your choices', function () {
    $user = User::factory()->notOnboarded()->create();

    Livewire::actingAs($user)->test(Welcome::class)
        ->set('goal', 'endurance')
        ->call('skip')
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->onboarded_at)->not->toBeNull()
        ->and($user->goal)->toBe(FitnessGoal::Endurance)
        ->and($user->workouts()->count())->toBe(0);
});
