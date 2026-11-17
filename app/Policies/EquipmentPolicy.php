<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

/**
 * Standaarditems (user_id = null) zijn voor iedereen zichtbaar maar niet te wijzigen.
 * Eigen items alleen voor de eigenaar.
 */
class EquipmentPolicy
{
    public function view(User $user, Equipment $model): bool
    {
        return $model->isStandard() || $model->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Equipment $model): bool
    {
        return $model->isManageableBy($user);
    }

    public function delete(User $user, Equipment $model): bool
    {
        return $model->isManageableBy($user);
    }
}
