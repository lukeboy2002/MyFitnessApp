<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Facades\DB;

/** Kant-en-klare workout overnemen: een eigen kopie die je kunt aanpassen; de standaardworkout blijft hetzelfde */
class AdoptWorkout
{
    public function handle(User $user, Workout $workout): Workout
    {
        $workout->loadMissing('exercises');

        return DB::transaction(fn () => $workout->duplicate($workout->name, $user));
    }
}
