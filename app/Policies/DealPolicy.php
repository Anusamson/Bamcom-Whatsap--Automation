<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Deal;
use App\Models\User;

class DealPolicy
{
    /**
     * Determine whether the user can view any deals.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::DealsView->value);
    }

    /**
     * Determine whether the user can view the deal.
     */
    public function view(User $user, Deal $deal): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::DealsView->value);
    }

    /**
     * Determine whether the user can create deals.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::DealsCreate->value);
    }

    /**
     * Determine whether the user can update the deal.
     */
    public function update(User $user, Deal $deal): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::DealsEdit->value);
    }

    /**
     * Determine whether the user can delete the deal.
     */
    public function delete(User $user, Deal $deal): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::DealsDelete->value);
    }
}
