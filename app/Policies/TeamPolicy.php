<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('teams.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->can('teams.view')
            || $team->leader_id === $user->id
            || $team->members()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('teams.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->can('teams.edit') || $team->leader_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        return $user->can('teams.delete');
    }

    /**
     * Determine whether the user can assign members to the team.
     */
    public function assign(User $user, Team $team): bool
    {
        return $user->can('teams.assign')
            || $user->can('teams.edit')
            || $team->leader_id === $user->id;
    }
}
