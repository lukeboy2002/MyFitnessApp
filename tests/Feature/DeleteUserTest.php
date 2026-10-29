<?php

use App\Enums\SessionStatus;
use App\Livewire\Profile\DeleteUser;
use App\Models\BodyMeasurement;
use App\Models\BodyPart;
use App\Models\BodyWeight;
use App\Models\Exercise;
use App\Models\Muscle;
use App\Models\MuscleGroup;
use App\Models\ScheduledWorkout;
use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(BodyPartSeeder::class);
    $this->user = User::factory()->create();

    foreach (['exercises/standaard.jpg', 'exercises/mijn.jpg', 'muscle-groups/mijn.jpg', 'body-parts/mijn.jpg', 'exercises/ander.jpg', 'profile-photos/ik.jpg'] as $file) {
        Storage::disk('public')->put($file, 'x');
    }
});

test('deleting your account removes your own images but never standard ones', function () {
    $other = User::factory()->create();
    $bodyPartId = BodyPart::whereNull('user_id')->value('id');

    Exercise::factory()->create(['user_id' => null, 'image_path' => 'exercises/standaard.jpg']);
    Exercise::factory()->create(['user_id' => $this->user->id, 'image_path' => 'exercises/mijn.jpg']);
    MuscleGroup::factory()->create(['user_id' => $this->user->id, 'body_part_id' => $bodyPartId, 'image_path' => 'muscle-groups/mijn.jpg']);
    BodyPart::factory()->create(['user_id' => $this->user->id, 'image_path' => 'body-parts/mijn.jpg']);
    Exercise::factory()->create(['user_id' => $other->id, 'image_path' => 'exercises/ander.jpg']);
    $this->user->forceFill(['profile_photo_path' => 'profile-photos/ik.jpg'])->save();

    // Een eigen oefening die (bijv. door oude data) naar het standaardplaatje wijst
    Exercise::factory()->create(['user_id' => $this->user->id, 'image_path' => 'exercises/standaard.jpg']);

    Livewire::actingAs($this->user)->test(DeleteUser::class)
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertRedirect('/');

    expect(User::find($this->user->id))->toBeNull();
    Storage::disk('public')->assertMissing(['exercises/mijn.jpg', 'muscle-groups/mijn.jpg', 'body-parts/mijn.jpg', 'profile-photos/ik.jpg']);
    Storage::disk('public')->assertExists(['exercises/standaard.jpg', 'exercises/ander.jpg']);
});

test('a wrong password deletes nothing', function () {
    Exercise::factory()->create(['user_id' => $this->user->id, 'image_path' => 'exercises/mijn.jpg']);

    Livewire::actingAs($this->user)->test(DeleteUser::class)
        ->set('password', 'fout')
        ->call('deleteUser')
        ->assertHasErrors('password');

    expect(User::find($this->user->id))->not->toBeNull();
    Storage::disk('public')->assertExists('exercises/mijn.jpg');
});

/** Van alles wat: eigen stamgegevens, workout, planning, afgeronde training, wegingen, maten, sessie en reset-token */
function fillAccount(User $user): void
{
    $bodyPart = BodyPart::factory()->create(['user_id' => $user->id]);
    $group = MuscleGroup::factory()->create(['user_id' => $user->id, 'body_part_id' => $bodyPart->id]);
    Muscle::factory()->create(['user_id' => $user->id, 'muscle_group_id' => $group->id]);
    $exercise = Exercise::factory()->create(['user_id' => $user->id]);
    $exercise->syncMuscleGroups($group->id);

    $workout = Workout::factory()->for($user)->create();
    $workout->muscleGroups()->attach($group);
    WorkoutExercise::factory()->for($workout)->create(['exercise_id' => $exercise->id]);
    $session = WorkoutSession::create([
        'user_id' => $user->id, 'workout_id' => $workout->id, 'name' => 'Push', 'color' => '#00468c', 'status' => SessionStatus::Completed,
        'started_at' => now()->subHour(), 'ended_at' => now(), 'duration_seconds' => 3600,
    ]);
    $session->exercises()->create(['exercise_id' => $exercise->id, 'position' => 1])
        ->sets()->create(['set_number' => 1, 'reps' => 10, 'weight' => 40, 'is_completed' => true]);
    ScheduledWorkout::create(['user_id' => $user->id, 'workout_id' => $workout->id, 'scheduled_on' => today(), 'workout_session_id' => $session->id]);

    $user->bodyWeights()->create(['measured_on' => today(), 'weight_kg' => 80]);
    $user->bodyMeasurements()->create(['measured_on' => today(), 'waist_cm' => 85]);
    DB::table('sessions')->insert(['id' => 'sessie-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'x']);
}

test('deleting your account removes all your data and leaves others untouched', function () {
    $other = User::factory()->create();
    fillAccount($this->user);
    fillAccount($other);

    $standard = [Exercise::whereNull('user_id')->count(), MuscleGroup::whereNull('user_id')->count(), BodyPart::whereNull('user_id')->count()];
    $countOther = fn () => [
        WorkoutSession::count(), SetLog::count(), Workout::count(), ScheduledWorkout::count(), Exercise::whereNotNull('user_id')->count(),
        Muscle::whereNotNull('user_id')->count(), MuscleGroup::whereNotNull('user_id')->count(), BodyPart::whereNotNull('user_id')->count(),
        BodyWeight::count(), BodyMeasurement::count(), DB::table('sessions')->count(), DB::table('password_reset_tokens')->count(),
    ];
    $before = $countOther();

    Livewire::actingAs($this->user)->test(DeleteUser::class)
        ->set('password', 'password')
        ->call('deleteUser');

    // Alles van de gebruiker is weg: elke telling is precies de helft (de andere gebruiker houdt het zijne)
    expect($countOther())->toBe(array_map(fn ($n) => intdiv($n, 2), $before))
        ->and(WorkoutSession::where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', $this->user->email)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and([Exercise::whereNull('user_id')->count(), MuscleGroup::whereNull('user_id')->count(), BodyPart::whereNull('user_id')->count()])->toBe($standard);
});
