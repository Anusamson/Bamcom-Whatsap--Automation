<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $task->assigned_user_id === $user->id
            || $task->created_by_id === $user->id
            || $user->hasPermissionTo('contacts.view');
    }

    /**
     * Determine whether the user can create tasks.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $task->assigned_user_id === $user->id
            || $task->created_by_id === $user->id
            || $user->hasPermissionTo('contacts.edit');
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $task->created_by_id === $user->id
            || $user->hasPermissionTo('contacts.delete');
    }
}
