<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\EmailMessage;
use App\Models\User;

class EmailMessagePolicy
{
    /**
     * Determine whether the user can view any email messages.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailsView->value);
    }

    /**
     * Determine whether the user can view the email message.
     */
    public function view(User $user, EmailMessage $emailMessage): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailsView->value);
    }

    /**
     * Determine whether the user can send outbound emails.
     */
    public function send(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailsSend->value);
    }

    /**
     * Determine whether the user can manage email accounts and infrastructure.
     */
    public function manage(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::EmailsManage->value);
    }
}
