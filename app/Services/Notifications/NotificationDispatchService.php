<?php

namespace App\Services\Notifications;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DealActivityNotification;
use App\Notifications\HotLeadNotification;
use App\Notifications\HumanHandoverNotification;
use App\Notifications\InspectionReminderNotification;
use App\Notifications\InspectionRequestNotification;
use App\Notifications\NewAssignedLeadNotification;
use App\Notifications\NewCustomerReplyNotification;
use App\Notifications\OverdueTaskNotification;
use Illuminate\Support\Collection;

/**
 * Centralized service to determine and notify appropriate staff for CRM events.
 */
class NotificationDispatchService
{
    /**
     * 1. Notify appropriate staff for new assigned lead.
     */
    public function notifyLeadAssigned(Lead $lead, ?User $causer = null): void
    {
        $assignedUser = $lead->assignedUser ?: ($lead->assigned_user_id ? User::find($lead->assigned_user_id) : null);

        if ($assignedUser && (! $causer || $assignedUser->id !== $causer->id)) {
            $assignedUser->notify(new NewAssignedLeadNotification($lead, $causer));
        }
    }

    /**
     * 2. Notify appropriate staff for hot lead.
     */
    public function notifyHotLead(Lead $lead, ?int $score = null): void
    {
        $recipients = collect();

        // Assigned Agent
        if ($lead->assignedUser) {
            $recipients->push($lead->assignedUser);
        }

        // Sales Managers & Super Admins
        $managers = $this->getUsersWithRoles([
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::SalesManager,
        ]);

        $recipients = $recipients->concat($managers)->unique('id');

        foreach ($recipients as $user) {
            $user->notify(new HotLeadNotification($lead, $score));
        }
    }

    /**
     * 3. Notify appropriate staff for human handover.
     */
    public function notifyHumanHandover(Conversation $conversation, string $triggerLabel = 'Customer Request', string $reason = 'Escalation'): void
    {
        $recipients = collect();

        if ($conversation->assignedUser) {
            $recipients->push($conversation->assignedUser);
        }

        // Also notify Support / Sales Executives / Admins
        $staff = $this->getUsersWithRoles([
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::CustomerSupport,
            UserRole::SalesExecutive,
        ]);

        $recipients = $recipients->concat($staff)->unique('id');

        foreach ($recipients as $user) {
            $user->notify(new HumanHandoverNotification($conversation, $triggerLabel, $reason));
        }
    }

    /**
     * 4. Notify appropriate staff for inspection request.
     */
    public function notifyInspectionRequested(Inspection $inspection): void
    {
        $recipients = collect();

        if ($inspection->representative) {
            $recipients->push($inspection->representative);
        }

        $officers = $this->getUsersWithRoles([
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::InspectionOfficer,
            UserRole::SalesManager,
        ]);

        $recipients = $recipients->concat($officers)->unique('id');

        foreach ($recipients as $user) {
            $user->notify(new InspectionRequestNotification($inspection));
        }
    }

    /**
     * 5. Notify appropriate staff for inspection reminder.
     */
    public function notifyInspectionReminder(Inspection $inspection, string $timing = 'Upcoming'): void
    {
        $recipient = $inspection->representative ?: ($inspection->representative_id ? User::find($inspection->representative_id) : null);

        if ($recipient) {
            $recipient->notify(new InspectionReminderNotification($inspection, $timing));
        }
    }

    /**
     * 6. Notify appropriate staff for overdue task.
     */
    public function notifyTaskOverdue(Task $task): void
    {
        $assignedUser = $task->assignedUser ?: ($task->assigned_user_id ? User::find($task->assigned_user_id) : null);

        if ($assignedUser) {
            $assignedUser->notify(new OverdueTaskNotification($task));
        }
    }

    /**
     * 7. Notify appropriate staff for new customer reply.
     */
    public function notifyCustomerReply(Message $message, Conversation $conversation): void
    {
        $recipients = collect();

        if ($conversation->assignedUser) {
            $recipients->push($conversation->assignedUser);
        } else {
            // Unassigned conversation - notify active Support and Admins
            $supportTeam = $this->getUsersWithRoles([
                UserRole::SuperAdmin,
                UserRole::Admin,
                UserRole::CustomerSupport,
            ]);
            $recipients = $recipients->concat($supportTeam);
        }

        $recipients = $recipients->unique('id');

        foreach ($recipients as $user) {
            $user->notify(new NewCustomerReplyNotification($message, $conversation));
        }
    }

    /**
     * 8. Notify appropriate staff for deal activity.
     */
    public function notifyDealActivity(Deal $deal, string $activityType = 'updated', ?string $detail = null, ?User $causer = null): void
    {
        $recipients = collect();

        if ($deal->assignedUser && (! $causer || $deal->assignedUser->id !== $causer->id)) {
            $recipients->push($deal->assignedUser);
        }

        // If deal is won or lost, also notify Sales Managers and Admins
        if (in_array($activityType, ['won', 'lost'])) {
            $managers = $this->getUsersWithRoles([
                UserRole::SuperAdmin,
                UserRole::Admin,
                UserRole::SalesManager,
            ]);
            $recipients = $recipients->concat($managers);
        }

        $recipients = $recipients->unique('id');

        foreach ($recipients as $user) {
            $user->notify(new DealActivityNotification($deal, $activityType, $detail));
        }
    }

    /**
     * Helper to retrieve active users matching given role enums.
     *
     * @param  array<int, UserRole>  $roles
     * @return Collection<int, User>
     */
    protected function getUsersWithRoles(array $roles): Collection
    {
        $roleValues = array_map(fn (UserRole $r) => $r->value, $roles);

        return User::where('status', 'active')
            ->whereIn('role', $roleValues)
            ->get();
    }
}
