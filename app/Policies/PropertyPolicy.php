<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Determine whether the user can view any properties.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::PropertiesView->value);
    }

    /**
     * Determine whether the user can view the property.
     */
    public function view(User $user, Property $property): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::PropertiesView->value);
    }

    /**
     * Determine whether the user can create properties.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::PropertiesCreate->value);
    }

    /**
     * Determine whether the user can update the property.
     */
    public function update(User $user, Property $property): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::PropertiesEdit->value);
    }

    /**
     * Determine whether the user can delete the property.
     */
    public function delete(User $user, Property $property): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::PropertiesDelete->value);
    }
}
