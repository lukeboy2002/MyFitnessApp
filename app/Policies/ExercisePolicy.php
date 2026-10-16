<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\User;

/**
 * Standaardoefeningen (user_id = null) zijn voor iedereen zichtbaar en te kopiëren, maar niet te wijzigen.
 * Eigen oefeningen alleen voor de eigenaar.
 */
class ExercisePolicy
{
    public function view(User $user, Exercise $exercise): bool
    {
        return $exercise->isStandard() || $exercise->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Exercise $exercise): bool
    {
        return $exercise->isOwnedBy($user);
    }

    public function delete(User $user, Exercise $exercise): bool
    {
        return $exercise->isOwnedBy($user);
    }
}
