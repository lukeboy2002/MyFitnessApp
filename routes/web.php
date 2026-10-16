<?php

use App\Livewire\BodyParts\Index;
use App\Livewire\Exercises\Form;
use App\Livewire\Exercises\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');

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

});
