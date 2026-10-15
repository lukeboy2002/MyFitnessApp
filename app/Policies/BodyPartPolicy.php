<?php

namespace App\Policies;

use App\Models\BodyPart;
use App\Models\User;

/**
 * Standaarditems (user_id = null) zijn voor iedereen zichtbaar maar niet te wijzigen.
 * Eigen items alleen voor de eigenaar.
 */
class BodyPartPolicy
{
    public function view(User $user, BodyPart $model): bool
    {
        return $model->isStandard() || $model->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BodyPart $model): bool
    {
        return $model->isOwnedBy($user);
    }

    public function delete(User $user, BodyPart $model): bool
    {
        return $model->isOwnedBy($user);
    }
}
