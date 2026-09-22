<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('users.view') || $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can('users.edit');
    }

    /**
     * Determine whether the user can disable/enable the model.
     */
    public function disable(User $user, User $model): bool
    {
        if ($model->isSuperAdmin()) {
            return false;
        }

        return $user->can('users.disable') || $user->can('users.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Never allow deleting Super Admin or self
        if ($model->isSuperAdmin() || $user->id === $model->id) {
            return false;
        }

        return $user->can('users.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Blocked if the model has recorded CRM activity.
     */
    public function forceDelete(User $user, User $model): bool
    {
        if ($model->isSuperAdmin() || $user->id === $model->id || $model->hasCrmActivity()) {
            return false;
        }

        return $user->can('users.delete');
    }
}
