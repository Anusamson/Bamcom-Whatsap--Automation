<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * Determine whether the user can view any leads.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::LeadsView->value);
    }

    /**
     * Determine whether the user can view the lead opportunity.
     */
    public function view(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::LeadsView->value);
    }

    /**
     * Determine whether the user can create leads.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::LeadsCreate->value);
    }

    /**
     * Determine whether the user can update the lead.
     */
    public function update(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::LeadsEdit->value);
    }

    /**
     * Determine whether the user can delete the lead.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::LeadsDelete->value);
    }

    /**
     * Determine whether the user can assign leads to agents.
     */
    public function assign(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::LeadsAssign->value);
    }
}
