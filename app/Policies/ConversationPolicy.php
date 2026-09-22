<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can view any conversations.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::ConversationsView->value);
    }

    /**
     * Determine whether the user can view the specific conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::ConversationsView->value);
    }

    /**
     * Determine whether the user can create conversations.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::ConversationsManage->value);
    }

    /**
     * Determine whether the user can update conversation details (mode, status).
     */
    public function update(User $user, Conversation $conversation): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo(PermissionEnum::ConversationsManage->value);
    }

    /**
     * Determine whether the user can assign/reassign conversation to an agent.
     */
    public function assign(User $user, Conversation $conversation): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermissionTo(PermissionEnum::ConversationsAssign->value)
            || $user->hasPermissionTo(PermissionEnum::ConversationsManage->value);
    }

    /**
     * Determine whether the user can post/send messages in the conversation.
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermissionTo(PermissionEnum::MessagesSend->value)
            || $user->hasPermissionTo(PermissionEnum::WhatsAppSend->value);
    }

    /**
     * Determine whether the user can delete a conversation.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        return $user->isSuperAdmin();
    }
}
