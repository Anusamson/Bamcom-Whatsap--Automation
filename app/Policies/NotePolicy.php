<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    /**
     * Determine whether the user can create notes.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the note.
     */
    public function update(User $user, Note $note): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $note->user_id === $user->id
            || $user->hasPermissionTo('contacts.edit');
    }

    /**
     * Determine whether the user can delete the note.
     */
    public function delete(User $user, Note $note): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || $note->user_id === $user->id
            || $user->hasPermissionTo('contacts.delete');
    }
}
