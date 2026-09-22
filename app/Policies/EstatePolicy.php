<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Estate;
use App\Models\User;

class EstatePolicy
{
    /**
     * Determine whether the user can view any estates.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::PropertiesView->value);
    }

    /**
     * Determine whether the user can view the estate.
     */
    public function view(User $user, Estate $estate): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::PropertiesView->value);
    }

    /**
     * Determine whether the user can create estates.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::PropertiesCreate->value);
    }

    /**
     * Determine whether the user can update the estate.
     */
    public function update(User $user, Estate $estate): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::PropertiesEdit->value);
    }

    /**
     * Determine whether the user can delete the estate.
     */
    public function delete(User $user, Estate $estate): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::PropertiesDelete->value);
    }
}
