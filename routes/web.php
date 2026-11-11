<?php

use App\Http\Controllers\Admin\PreviewController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\UsernameAvailabilityController;
use App\Livewire\Admin\Badges as AdminBadges;
use App\Livewire\Admin\Settings as AdminSettings;
use App\Livewire\Admin\Users as AdminUsers;
use App\Livewire\Badges;
use App\Livewire\BodyParts\Index as BodyPartsIndex;
use App\Livewire\Calendar;
use App\Livewire\Dashboard;
use App\Livewire\Exercises\Form as ExerciseForm;
use App\Livewire\Exercises\Index as ExercisesIndex;
use App\Livewire\Exercises\Show as ExerciseShow;
use App\Livewire\MuscleGroups\Index as MuscleGroupsIndex;
use App\Livewire\Muscles\Index as MusclesIndex;
use App\Livewire\Progress\Exercise as ProgressExercise;
use App\Livewire\Progress\Index as ProgressIndex;
use App\Livewire\Sessions\Quickstart;
use App\Livewire\Sessions\Run;
use App\Livewire\Sessions\Summary;
use App\Livewire\Welcome;
use App\Livewire\Workouts\Form as WorkoutForm;
use App\Livewire\Workouts\Index as WorkoutsIndex;
use App\Livewire\Workouts\Show as WorkoutShow;
use Illuminate\Support\Facades\Route;

// Registreren: is een gebruikersnaam nog vrij? (stap 1 van het formulier)
Route::get('register/username', UsernameAvailabilityController::class)
    ->middleware(['guest', 'throttle:30,1'])->name('register.username');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard en welkomstscherm (eerste keer inloggen)
    Route::get('/', Dashboard::class)->name('dashboard');
    // Fortify/passkeys sturen standaard naar /dashboard; het dashboard staat op /
    Route::redirect('dashboard', '/');
    Route::get('welcome', Welcome::class)->name('welcome');

    // Profiel
    Route::view('profile', 'profile.edit')->name('profile.edit');
    Route::get('profile/export/{type}', ExportController::class)->middleware('throttle:10,1')->name('profile.export');

    // Pushmeldingen voor dit apparaat (rust voorbij)
    Route::post('push/subscription', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('push/subscription', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::post('push/test', [PushSubscriptionController::class, 'test'])->middleware('throttle:5,1')->name('push.test');

    // Stamgegevens
    Route::get('body-parts', BodyPartsIndex::class)->name('body-parts.index');
    Route::get('muscle-groups', MuscleGroupsIndex::class)->name('muscle-groups.index');
    Route::get('muscles', MusclesIndex::class)->name('muscles.index');

    // Oefeningen
    Route::get('exercises', ExercisesIndex::class)->name('exercises.index');
    Route::get('exercises/create', ExerciseForm::class)->name('exercises.create');
    Route::get('exercises/{exercise}', ExerciseShow::class)->name('exercises.show');
    Route::get('exercises/{exercise}/edit', ExerciseForm::class)->name('exercises.edit');

    // Workouts
    Route::get('workouts', WorkoutsIndex::class)->name('workouts.index');
    Route::get('workouts/create', WorkoutForm::class)->name('workouts.create');
    Route::get('workouts/{workout}', WorkoutShow::class)->name('workouts.show');
    Route::get('workouts/{workout}/edit', WorkoutForm::class)->name('workouts.edit');

    // Training uitvoeren
    Route::get('quickstart', Quickstart::class)->name('quickstart');
    Route::get('sessions/{session}', Run::class)->name('sessions.run');
    Route::get('sessions/{session}/summary', Summary::class)->name('sessions.summary');

    // Kalender
    Route::get('calendar', Calendar::class)->name('calendar');

    // Progress
    Route::get('progress', ProgressIndex::class)->name('progress.index');
    Route::get('progress/{exercise}', ProgressExercise::class)->name('progress.exercise');

    // Badges
    Route::get('badges', Badges::class)->name('badges');

    // Beheer (alleen admins)
    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', AdminUsers::class)->name('users');
        Route::get('settings', AdminSettings::class)->name('settings');
        Route::get('badges', AdminBadges::class)->name('badges');
        Route::get('preview/mail/{mail}', [PreviewController::class, 'mail'])->name('preview.mail');
        Route::get('preview/error/{code}', [PreviewController::class, 'error'])->whereNumber('code')->name('preview.error');
    });
});
