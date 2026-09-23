<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Inspection;
use App\Models\User;

class InspectionPolicy
{
    /**
     * Determine whether the user can view any inspections.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $user->hasPermissionTo(PermissionEnum::InspectionsView->value);
    }

    /**
     * Determine whether the user can view the inspection.
     */
    public function view(User $user, Inspection $inspection): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $user->hasPermissionTo(PermissionEnum::InspectionsView->value)
            || $inspection->representative_id === $user->id;
    }

    /**
     * Determine whether the user can schedule inspections.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $user->hasPermissionTo(PermissionEnum::InspectionsCreate->value);
    }

    /**
     * Determine whether the user can update the inspection.
     */
    public function update(User $user, Inspection $inspection): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $user->hasPermissionTo(PermissionEnum::InspectionsEdit->value)
            || $inspection->representative_id === $user->id;
    }

    /**
     * Determine whether the user can approve or complete the inspection.
     */
    public function approve(User $user, Inspection $inspection): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $user->hasPermissionTo(PermissionEnum::InspectionsApprove->value)
            || $user->hasPermissionTo(PermissionEnum::InspectionsEdit->value);
    }

    /**
     * Determine whether the user can delete the inspection.
     */
    public function delete(User $user, Inspection $inspection): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $user->hasPermissionTo(PermissionEnum::InspectionsEdit->value);
    }
}
