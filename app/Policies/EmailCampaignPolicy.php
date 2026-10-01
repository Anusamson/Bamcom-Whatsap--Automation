<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\EmailCampaign;
use App\Models\User;

class EmailCampaignPolicy
{
    /**
     * Determine whether the user can view any email campaigns.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermissionTo(PermissionEnum::EmailCampaignsView->value)
            || $user->hasPermissionTo(PermissionEnum::CampaignsView->value);
    }

    /**
     * Determine whether the user can view the specific email campaign.
     */
    public function view(User $user, EmailCampaign $campaign): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermissionTo(PermissionEnum::EmailCampaignsView->value)
            || $user->hasPermissionTo(PermissionEnum::CampaignsView->value);
    }

    /**
     * Determine whether the user can create email campaigns.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermissionTo(PermissionEnum::EmailCampaignsManage->value)
            || $user->hasPermissionTo(PermissionEnum::CampaignsCreate->value);
    }

    /**
     * Determine whether the user can update the email campaign.
     */
    public function update(User $user, EmailCampaign $campaign): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermissionTo(PermissionEnum::EmailCampaignsManage->value)
            || $user->hasPermissionTo(PermissionEnum::CampaignsEdit->value);
    }

    /**
     * Determine whether the user can delete the email campaign.
     */
    public function delete(User $user, EmailCampaign $campaign): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermissionTo(PermissionEnum::EmailCampaignsManage->value)
            || $user->hasPermissionTo(PermissionEnum::CampaignsDelete->value);
    }
}
