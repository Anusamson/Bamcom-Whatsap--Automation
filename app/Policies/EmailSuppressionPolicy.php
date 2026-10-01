<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\EmailSuppression;
use App\Models\User;

class EmailSuppressionPolicy
{
    /**
     * Determine whether the user can view suppressions.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailSuppressionsView->value);
    }

    /**
     * Determine whether the user can create suppressions.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailSuppressionsManage->value);
    }

    /**
     * Determine whether the user can remove a suppression.
     */
    public function delete(User $user, EmailSuppression $suppression): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailSuppressionsManage->value);
    }
}
