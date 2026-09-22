<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    /**
     * Determine whether the user can view any contacts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::ContactsView->value);
    }

    /**
     * Determine whether the user can view the contact 360 profile.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::ContactsView->value);
    }

    /**
     * Determine whether the user can create contacts.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::ContactsCreate->value);
    }

    /**
     * Determine whether the user can update the contact.
     */
    public function update(User $user, Contact $contact): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::ContactsEdit->value);
    }

    /**
     * Determine whether the user can delete the contact.
     */
    public function delete(User $user, Contact $contact): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::ContactsDelete->value);
    }

    /**
     * Determine whether the user can assign contacts to sales agents.
     */
    public function assign(User $user, Contact $contact): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::ContactsAssign->value);
    }
}
