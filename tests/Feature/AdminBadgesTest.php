<?php

use App\Enums\BadgeMetric;
use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Livewire\Admin\Badges;
use App\Models\Badge;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\Achievements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->forceFill(['is_admin' => true])->save();
});

test('the standard badges come with the migration and only admins manage them', function () {
    expect(Badge::count())->toBe(13)
        ->and(Badge::where('metric', 'sessions')->first()->labelFor(0))->toBe('Eerste training')
        ->and(Badge::where('metric', 'volume')->first()->labelFor(1))->toBe('50.000 kg getild');

    $this->actingAs(User::factory()->create())->get(route('admin.badges'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.badges'))->assertOk()->assertSee(['Badgebeheer', 'Weekendstrijder', '1 · 10 · 25']);
});

test('an admin changes thresholds and they count right away', function () {
    $badge = Badge::where('metric', 'sessions')->first();

    Livewire::actingAs($this->admin)->test(Badges::class)
        ->call('edit', $badge->id)
        ->assertSet('thresholds', '1, 10, 25, 50, 100, 250, 500')
        ->set('thresholds', '25, 10')
        ->call('save')
        ->assertHasErrors('thresholds')
        ->set('thresholds', '1, 2, 3')
        ->assertSet('preview', ['Eerste training', '2 trainingen', '3 trainingen'])
        ->call('save')
        ->assertHasNoErrors();

    expect($badge->fresh()->thresholds)->toBe([1, 2, 3]);
});

test('an admin adds a badge for one exercise', function () {
    $treadmill = Exercise::factory()->create(['name' => 'Loopband', 'type' => ExerciseType::Cardio]);
    $user = User::factory()->create();
    foreach (['2026-10-05 18:00', '2026-10-07 18:00'] as $start) {
        WorkoutSession::create(['user_id' => $user->id, 'name' => 'Cardio', 'status' => SessionStatus::Completed, 'started_at' => $start, 'duration_seconds' => 3600])
            ->exercises()->create(['exercise_id' => $treadmill->id, 'position' => 1])
            ->cardio()->create(['duration_seconds' => 1200, 'distance_m' => 1800]);
    }

    Livewire::actingAs($this->admin)->test(Badges::class)
        ->call('create')
        ->set('name', 'Loopbandkoning')
        ->set('icon', 'run')
        ->set('metric', BadgeMetric::ExerciseDistance->value)
        ->call('save')
        ->assertHasErrors(['exercise_id', 'thresholds', 'label'])
        ->set('exercise_id', $treadmill->id)
        ->set('thresholds', '1.5, 3, 10')
        ->set('label', '{n} km op de loopband')
        ->call('save')
        ->assertHasNoErrors();

    $progress = Achievements::for($user)->first(fn ($progress) => $progress->name === 'Loopbandkoning');
    expect($progress->value)->toEqual(3.6)
        ->and($progress->level())->toBe(2)
        ->and($progress->title())->toBe('3 km op de loopband');
});

test('an admin turns off and deletes badges', function () {
    $badge = Badge::where('metric', 'late')->first();

    Livewire::actingAs($this->admin)->test(Badges::class)
        ->call('toggleActive', $badge->id)
        ->call('confirmDelete', Badge::where('metric', 'early')->first()->id)
        ->assertSet('deletingName', 'Vroege vogel')
        ->call('deleteConfirmed');

    expect($badge->fresh()->is_active)->toBeFalse()
        ->and(Badge::where('metric', 'early')->exists())->toBeFalse()
        ->and(Achievements::for(User::factory()->create())->pluck('name'))->not->toContain('Nachtuil', 'Vroege vogel');
});

test('only admins can change badges', function () {
    Livewire::actingAs(User::factory()->create())->test(Badges::class)->assertForbidden();
});
