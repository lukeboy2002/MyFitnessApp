<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workout;

/** Workouts zijn altijd van één gebruiker */
class WorkoutPolicy
{
    public function view(User $user, Workout $workout): bool
    {
        return $workout->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workout $workout): bool
    {
        return $workout->isOwnedBy($user);
    }

    public function delete(User $user, Workout $workout): bool
    {
        return $workout->isOwnedBy($user);
    }
}
