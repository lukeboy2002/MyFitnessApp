<?php

namespace App\Policies;

use App\Models\Muscle;
use App\Models\User;

/**
 * Standaarditems (user_id = null) zijn voor iedereen zichtbaar maar niet te wijzigen.
 * Eigen items alleen voor de eigenaar.
 */
class MusclePolicy
{
    public function view(User $user, Muscle $model): bool
    {
        return $model->isStandard() || $model->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Muscle $model): bool
    {
        return $model->isOwnedBy($user);
    }

    public function delete(User $user, Muscle $model): bool
    {
        return $model->isOwnedBy($user);
    }
}
