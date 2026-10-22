<?php

use App\Livewire\Admin\Badges as AdminBadges;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\Users;
use App\Livewire\Badges;
use App\Livewire\BodyParts\Index;
use App\Livewire\Calendar;
use App\Livewire\Dashboard;
use App\Livewire\Exercises\Form;
use App\Livewire\Exercises\Show;
use App\Livewire\Progress\Exercise;
use App\Livewire\Sessions\Quickstart;
use App\Livewire\Sessions\Run;
use App\Livewire\Sessions\Summary;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    // Fortify/passkeys sturen standaard naar /dashboard; het dashboard staat op /
    Route::redirect('dashboard', '/');

    Route::view('profile', 'profile.edit')->name('profile.edit');

    // Stamgegevens
    Route::get('body-parts', Index::class)->name('body-parts.index');
    Route::get('muscle-groups', App\Livewire\MuscleGroups\Index::class)->name('muscle-groups.index');
    Route::get('muscles', App\Livewire\Muscles\Index::class)->name('muscles.index');

    // Oefeningen
    Route::get('exercises', App\Livewire\Exercises\Index::class)->name('exercises.index');
    Route::get('exercises/create', Form::class)->name('exercises.create');
    Route::get('exercises/{exercise}', Show::class)->name('exercises.show');
    Route::get('exercises/{exercise}/edit', Form::class)->name('exercises.edit');

    // Workouts
    Route::get('workouts', App\Livewire\Workouts\Index::class)->name('workouts.index');
    Route::get('workouts/create', App\Livewire\Workouts\Form::class)->name('workouts.create');
    Route::get('workouts/{workout}', App\Livewire\Workouts\Show::class)->name('workouts.show');
    Route::get('workouts/{workout}/edit', App\Livewire\Workouts\Form::class)->name('workouts.edit');

    // Kalender
    Route::get('calendar', Calendar::class)->name('calendar');

    Route::get('admin/users', Users::class)->middleware('can:admin')->name('admin.users');
    Route::get('admin/settings', Settings::class)->middleware('can:admin')->name('admin.settings');
    Route::get('admin/badges', AdminBadges::class)->middleware('can:admin')->name('admin.badges');

    // Progress
    // Badges
    Route::get('badges', Badges::class)->name('badges');

    Route::get('progress', App\Livewire\Progress\Index::class)->name('progress.index');
    Route::get('progress/{exercise}', Exercise::class)->name('progress.exercise');

    // Training uitvoeren
    Route::get('quickstart', Quickstart::class)->name('quickstart');
    Route::get('sessions/{session}', Run::class)->name('sessions.run');
    Route::get('sessions/{session}/summary', Summary::class)->name('sessions.summary');

});
