<?php

namespace App\Policies;

use App\Models\SharedWorkout;
use App\Models\User;
use App\Models\Workout;

/**
 * Eigen workouts zijn van één gebruiker. Standaardworkouts (user_id null, "Kant-en-klaar") kan iedereen
 * bekijken en overnemen; alleen de admin wijzigt ze.
 */
class WorkoutPolicy
{
    /** Eigen, kant-en-klaar, of door een maatje naar je gestuurd (zolang je nog niet hebt gekozen) */
    public function view(User $user, Workout $workout): bool
    {
        return $workout->isOwnedBy($user)
            || $workout->isStandard()
            || SharedWorkout::open()->where('workout_id', $workout->id)->where('recipient_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function createStandard(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Workout $workout): bool
    {
        return $workout->isManageableBy($user);
    }

    public function delete(User $user, Workout $workout): bool
    {
        return $workout->isManageableBy($user);
    }

    /** Een training starten kan alleen met een eigen workout; een standaardworkout neem je eerst over */
    public function start(User $user, Workout $workout): bool
    {
        return $workout->isOwnedBy($user);
    }

    /** Delen met maatjes: alleen je eigen workouts */
    public function share(User $user, Workout $workout): bool
    {
        return $workout->isOwnedBy($user);
    }

    public function adopt(User $user, Workout $workout): bool
    {
        return $workout->isStandard() && ! $workout->isArchived();
    }
}
