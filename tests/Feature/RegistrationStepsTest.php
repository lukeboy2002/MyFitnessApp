<?php

use App\Enums\ExperienceLevel;
use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function registration(array $extra = []): array
{
    return [
        'name' => 'Jan Jansen',
        'username' => 'janjansen',
        'email' => 'jan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$extra,
    ];
}

test('the register page shows the three steps', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('1. Account')
        ->assertSee('2. Over jou')
        ->assertSee('3. Je doel')
        ->assertSee('Waar train je voor?');
});

test('personal details, goal and first weight are saved when registering', function () {
    $this->post(route('register'), registration([
        'birth_date' => '1980-04-12',
        'gender' => 'female',
        'height_cm' => '170',
        'experience' => 'beginner',
        'goal' => 'lose_weight',
        'weight_kg' => '72,4',
        'target_weight_kg' => '68',
        'weekly_goal_hours' => '3,5',
    ]))->assertSessionHasNoErrors();

    $user = User::sole();

    expect($user->birth_date->format('Y-m-d'))->toBe('1980-04-12')
        ->and($user->gender)->toBe(Gender::Female)
        ->and($user->height_cm)->toBe(170)
        ->and($user->experience)->toBe(ExperienceLevel::Beginner)
        ->and($user->goal)->toBe(FitnessGoal::LoseWeight)
        ->and((float) $user->target_weight_kg)->toBe(68.0)
        ->and($user->weekly_goal_minutes)->toBe(210)
        ->and($user->bodyWeights()->count())->toBe(1)
        ->and((float) $user->bodyWeights()->first()->weight_kg)->toBe(72.4);
});

test('the extra steps are optional', function () {
    $this->post(route('register'), registration([
        'birth_date' => '',
        'gender' => '',
        'height_cm' => '',
        'goal' => '',
        'weight_kg' => '',
        'weekly_goal_hours' => '',
    ]))->assertSessionHasNoErrors();

    $user = User::sole();

    expect($user->birth_date)->toBeNull()
        ->and($user->goal)->toBeNull()
        ->and($user->weekly_goal_minutes)->toBeNull()
        ->and($user->bodyWeights()->count())->toBe(0);
});

test('invalid details are refused', function () {
    $this->post(route('register'), registration([
        'height_cm' => '20',
        'weight_kg' => '5',
        'goal' => 'fly',
    ]))->assertSessionHasErrors(['height_cm', 'weight_kg', 'goal']);

    expect(User::count())->toBe(0);
});
