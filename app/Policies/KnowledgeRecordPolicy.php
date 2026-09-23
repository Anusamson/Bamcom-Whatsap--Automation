<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\KnowledgeRecord;
use App\Models\User;

class KnowledgeRecordPolicy
{
    /**
     * Determine whether the user can view any knowledge records.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::KnowledgeView->value);
    }

    /**
     * Determine whether the user can view the knowledge record.
     */
    public function view(User $user, KnowledgeRecord $record): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::KnowledgeView->value);
    }

    /**
     * Determine whether the user can create knowledge records.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::KnowledgeCreate->value);
    }

    /**
     * Determine whether the user can update the knowledge record.
     */
    public function update(User $user, KnowledgeRecord $record): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::KnowledgeEdit->value);
    }

    /**
     * Determine whether the user can delete the knowledge record.
     */
    public function delete(User $user, KnowledgeRecord $record): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::KnowledgeDelete->value);
    }
}
