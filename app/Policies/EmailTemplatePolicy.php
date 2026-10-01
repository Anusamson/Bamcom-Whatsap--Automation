<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\EmailTemplate;
use App\Models\User;

class EmailTemplatePolicy
{
    /**
     * Determine whether the user can view any email templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailTemplatesView->value);
    }

    /**
     * Determine whether the user can view the email template.
     */
    public function view(User $user, EmailTemplate $template): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailTemplatesView->value);
    }

    /**
     * Determine whether the user can create email templates.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailTemplatesManage->value);
    }

    /**
     * Determine whether the user can update the email template.
     */
    public function update(User $user, EmailTemplate $template): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailTemplatesManage->value);
    }

    /**
     * Determine whether the user can delete the email template.
     */
    public function delete(User $user, EmailTemplate $template): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailTemplatesManage->value);
    }
}
