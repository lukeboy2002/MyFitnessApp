<?php

namespace App\Policies;

use App\Models\MuscleGroup;
use App\Models\User;

/**
 * Standaarditems (user_id = null) zijn voor iedereen zichtbaar maar niet te wijzigen.
 * Eigen items alleen voor de eigenaar.
 */
class MuscleGroupPolicy
{
    public function view(User $user, MuscleGroup $model): bool
    {
        return $model->isStandard() || $model->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MuscleGroup $model): bool
    {
        return $model->isManageableBy($user);
    }

    public function delete(User $user, MuscleGroup $model): bool
    {
        return $model->isManageableBy($user);
    }
}
