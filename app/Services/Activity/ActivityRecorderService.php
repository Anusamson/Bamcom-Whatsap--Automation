<?php

namespace App\Services\Activity;

use App\Enums\ContactStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;

/**
 * Centralized CRM Activity Recording & Timeline Service.
 */
class ActivityRecorderService
{
    /**
     * Record a standard CRM activity audit event.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $type,
        string $description,
        ?Contact $contact = null,
        ?Lead $lead = null,
        ?User $actor = null,
        array $properties = [],
        ?Deal $deal = null
    ): Activity {
        // Resolve contact from lead if not directly provided
        if (! $contact && $lead) {
            $contact = $lead->contact;
        }

        // Resolve lead from contact's latest lead if not provided
        if (! $lead && $contact) {
            $lead = $contact->leads()->latest()->first();
        }

        $userId = $actor?->id ?? auth()->id();

        return Activity::create([
            'contact_id' => $contact?->id,
            'lead_id' => $lead?->id,
            'deal_id' => $deal?->id,
            'user_id' => $userId,
            'activity_type' => $type,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    /**
     * Record activity when a task is created.
     */
    public function recordTaskCreated(Task $task, ?User $actor = null): Activity
    {
        $contact = $task->contact;
        $lead = $task->lead;
        $assignee = $task->assignedUser?->name ?? 'Unassigned';

        return $this->record(
            type: 'task_created',
            description: "Task created: \"{$task->title}\" assigned to {$assignee} (Due: {$task->due_at->format('M j, Y g:i A')})",
            contact: $contact,
            lead: $lead,
            actor: $actor,
            properties: [
                'task_id' => $task->id,
                'task_uuid' => $task->uuid,
                'title' => $task->title,
                'priority' => $task->priority->value,
                'type' => $task->type->value,
                'due_at' => $task->due_at->toIso8601String(),
                'assigned_user_id' => $task->assigned_user_id,
                'assigned_user_name' => $assignee,
            ],
            deal: $task->deal
        );
    }

    /**
     * Record activity when a task is completed.
     */
    public function recordTaskCompleted(Task $task, ?User $actor = null): Activity
    {
        return $this->record(
            type: 'task_completed',
            description: "Completed task: \"{$task->title}\"",
            contact: $task->contact,
            lead: $task->lead,
            actor: $actor,
            properties: [
                'task_id' => $task->id,
                'task_uuid' => $task->uuid,
                'title' => $task->title,
                'completed_at' => $task->completed_at?->toIso8601String() ?? now()->toIso8601String(),
            ],
            deal: $task->deal
        );
    }

    /**
     * Record activity when a task is cancelled.
     */
    public function recordTaskCancelled(Task $task, ?User $actor = null, ?string $reason = null): Activity
    {
        $desc = "Cancelled task: \"{$task->title}\"";
        if ($reason) {
            $desc .= " (Reason: {$reason})";
        }

        return $this->record(
            type: 'task_cancelled',
            description: $desc,
            contact: $task->contact,
            lead: $task->lead,
            actor: $actor,
            properties: [
                'task_id' => $task->id,
                'title' => $task->title,
                'reason' => $reason,
            ],
            deal: $task->deal
        );
    }

    /**
     * Record activity when a note is added.
     */
    public function recordNoteAdded(Note $note, ?User $actor = null): Activity
    {
        $snippet = mb_substr($note->content, 0, 100);
        if (mb_strlen($note->content) > 100) {
            $snippet .= '...';
        }

        return $this->record(
            type: 'note_added',
            description: "Internal note added: \"{$snippet}\"",
            contact: $note->contact,
            lead: $note->lead,
            actor: $actor ?? $note->user,
            properties: [
                'note_id' => $note->id,
                'note_uuid' => $note->uuid,
                'is_pinned' => $note->is_pinned,
            ]
        );
    }

    /**
     * Record activity when a contact is created.
     */
    public function recordContactCreated(Contact $contact, ?User $actor = null): Activity
    {
        return $this->record(
            type: 'contact_created',
            description: "Contact {$contact->full_name} registered in CRM via {$contact->lead_source->label()}",
            contact: $contact,
            actor: $actor,
            properties: [
                'source' => $contact->lead_source->value,
                'phone' => $contact->phone,
                'status' => $contact->status->value,
            ]
        );
    }

    /**
     * Record activity when contact status changes.
     */
    public function recordContactStatusChanged(
        Contact $contact,
        ContactStatus|string $oldStatus,
        ContactStatus|string $newStatus,
        ?User $actor = null
    ): Activity {
        $oldVal = $oldStatus instanceof ContactStatus ? $oldStatus->value : $oldStatus;
        $newVal = $newStatus instanceof ContactStatus ? $newStatus->value : $newStatus;

        return $this->record(
            type: 'contact_status_changed',
            description: "Contact lifecycle updated from '{$oldVal}' to '{$newVal}'",
            contact: $contact,
            actor: $actor,
            properties: [
                'old_status' => $oldVal,
                'new_status' => $newVal,
            ]
        );
    }

    /**
     * Record activity when an inspection is scheduled.
     */
    public function recordInspectionScheduled(Inspection $inspection, ?User $actor = null): Activity
    {
        $target = $inspection->property?->title ?? $inspection->estate_name ?? 'Property Inspection';
        $repName = $inspection->representative?->name ?? 'Assigned Representative';

        return $this->record(
            type: 'inspection_scheduled',
            description: "Site inspection scheduled for {$target} on {$inspection->inspection_date} at {$inspection->inspection_time} with {$repName}",
            contact: $inspection->contact,
            lead: $inspection->lead,
            actor: $actor,
            properties: [
                'inspection_id' => $inspection->id,
                'inspection_uuid' => $inspection->uuid,
                'target' => $target,
                'inspection_date' => $inspection->inspection_date,
                'inspection_time' => $inspection->inspection_time,
                'meeting_point' => $inspection->meeting_point,
                'representative_id' => $inspection->representative_id,
            ]
        );
    }

    /**
     * Record activity when an inspection is completed.
     */
    public function recordInspectionCompleted(Inspection $inspection, ?User $actor = null): Activity
    {
        $target = $inspection->property?->title ?? $inspection->estate_name ?? 'Property Inspection';

        return $this->record(
            type: 'inspection_completed',
            description: "Site inspection completed for {$target}".($inspection->outcome ? ": {$inspection->outcome}" : ''),
            contact: $inspection->contact,
            lead: $inspection->lead,
            actor: $actor,
            properties: [
                'inspection_id' => $inspection->id,
                'target' => $target,
                'outcome' => $inspection->outcome,
            ]
        );
    }

    /**
     * Build a unified, chronological Contact 360 Activity Timeline.
     *
     * Consolidates:
     * - Activities (CRM audit events, status transitions, handovers, inspections)
     * - Notes (authored notes with pinned priority)
     * - Tasks (actionable items with due date and completion state)
     * - Messages (WhatsApp incoming/outgoing messages)
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getContactTimeline(Contact $contact, array $filters = [], int $perPage = 50): array
    {
        $timelineItems = collect();
        $categoryFilter = $filters['category'] ?? 'all';

        // 1. CRM Activities
        if (in_array($categoryFilter, ['all', 'activity', 'activities'], true)) {
            $activities = Activity::query()
                ->where('contact_id', $contact->id)
                ->with(['user', 'lead', 'deal'])
                ->latest('created_at')
                ->limit($perPage)
                ->get();

            foreach ($activities as $act) {
                $timelineItems->push([
                    'id' => "activity_{$act->id}",
                    'raw_id' => $act->id,
                    'category' => 'activity',
                    'activity_type' => $act->activity_type,
                    'title' => $this->formatActivityTitle($act->activity_type),
                    'description' => $act->description,
                    'actor' => $act->user ? [
                        'id' => $act->user->id,
                        'name' => $act->user->name,
                        'role' => $act->user->role,
                    ] : null,
                    'properties' => $act->properties ?? [],
                    'created_at' => $act->created_at?->toIso8601String(),
                    'timestamp' => $act->created_at?->timestamp ?? 0,
                    'is_pinned' => false,
                    'icon' => $this->resolveActivityIcon($act->activity_type),
                    'badge' => $this->resolveActivityBadge($act->activity_type),
                ]);
            }
        }

        // 2. Notes
        if (in_array($categoryFilter, ['all', 'note', 'notes'], true)) {
            $notes = Note::query()
                ->where('contact_id', $contact->id)
                ->with('user')
                ->latest('created_at')
                ->limit($perPage)
                ->get();

            foreach ($notes as $note) {
                $timelineItems->push([
                    'id' => "note_{$note->id}",
                    'raw_id' => $note->id,
                    'category' => 'note',
                    'activity_type' => 'note',
                    'title' => $note->is_pinned ? 'Pinned Memo' : 'Sales Note',
                    'description' => $note->content,
                    'actor' => $note->user ? [
                        'id' => $note->user->id,
                        'name' => $note->user->name,
                        'role' => $note->user->role,
                    ] : null,
                    'properties' => ['is_pinned' => $note->is_pinned],
                    'created_at' => $note->created_at?->toIso8601String(),
                    'timestamp' => $note->created_at?->timestamp ?? 0,
                    'is_pinned' => (bool) $note->is_pinned,
                    'icon' => 'FileText',
                    'badge' => $note->is_pinned
                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800'
                        : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
                ]);
            }
        }

        // 3. Tasks
        if (in_array($categoryFilter, ['all', 'task', 'tasks'], true)) {
            $tasks = Task::query()
                ->where('contact_id', $contact->id)
                ->with(['assignedUser', 'creator'])
                ->latest('created_at')
                ->limit($perPage)
                ->get();

            foreach ($tasks as $task) {
                $timelineItems->push([
                    'id' => "task_{$task->id}",
                    'raw_id' => $task->id,
                    'category' => 'task',
                    'activity_type' => 'task',
                    'title' => "Task: {$task->title}",
                    'description' => $task->description ?? "Due by {$task->due_at->format('M j, Y g:i A')}",
                    'actor' => $task->assignedUser ? [
                        'id' => $task->assignedUser->id,
                        'name' => $task->assignedUser->name,
                        'role' => $task->assignedUser->role,
                    ] : null,
                    'properties' => [
                        'priority' => $task->priority->value,
                        'priority_label' => $task->priority->label(),
                        'status' => $task->status->value,
                        'status_label' => $task->status->label(),
                        'type' => $task->type->value,
                        'type_label' => $task->type->label(),
                        'due_at' => $task->due_at->toIso8601String(),
                        'is_completed' => $task->status === TaskStatus::Completed,
                    ],
                    'created_at' => $task->created_at?->toIso8601String(),
                    'timestamp' => $task->created_at?->timestamp ?? 0,
                    'is_pinned' => false,
                    'icon' => $task->status === TaskStatus::Completed ? 'CheckCircle2' : 'CheckSquare',
                    'badge' => $task->status->badgeClass(),
                ]);
            }
        }

        // 4. WhatsApp Messages (if available)
        if (in_array($categoryFilter, ['all', 'message', 'messages', 'whatsapp'], true)) {
            if (method_exists($contact, 'messages')) {
                $messages = $contact->messages()
                    ->latest()
                    ->limit(20)
                    ->get();

                foreach ($messages as $msg) {
                    $isInbound = ($msg->direction ?? 'inbound') === 'inbound';
                    $timelineItems->push([
                        'id' => "msg_{$msg->id}",
                        'raw_id' => $msg->id,
                        'category' => 'message',
                        'activity_type' => 'whatsapp_message',
                        'title' => $isInbound ? 'Incoming WhatsApp Message' : 'Outbound WhatsApp Response',
                        'description' => $msg->body ?? $msg->content ?? '',
                        'actor' => $isInbound
                            ? ['name' => $contact->full_name]
                            : ['name' => 'Bamcom AI / Agent'],
                        'properties' => [
                            'direction' => $msg->direction ?? 'inbound',
                            'channel' => 'whatsapp',
                        ],
                        'created_at' => $msg->created_at?->toIso8601String(),
                        'timestamp' => $msg->created_at?->timestamp ?? 0,
                        'is_pinned' => false,
                        'icon' => 'MessageSquare',
                        'badge' => $isInbound
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                            : 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                    ]);
                }
            }
        }

        // Sort: Pinned notes first, then chronological descending
        $sorted = $timelineItems->sort(function (array $a, array $b): int {
            if ($a['is_pinned'] && ! $b['is_pinned']) {
                return -1;
            }
            if (! $a['is_pinned'] && $b['is_pinned']) {
                return 1;
            }

            return $b['timestamp'] <=> $a['timestamp'];
        })->values();

        return [
            'items' => $sorted->all(),
            'total' => $sorted->count(),
            'pinned_count' => $sorted->where('is_pinned', true)->count(),
        ];
    }

    /**
     * Human readable title from activity type.
     */
    protected function formatActivityTitle(string $type): string
    {
        return match ($type) {
            'task_created' => 'Sales Task Created',
            'task_completed' => 'Task Completed',
            'task_cancelled' => 'Task Cancelled',
            'note_added' => 'Internal Note Added',
            'contact_created' => 'Contact Registered',
            'contact_status_changed' => 'Lifecycle Status Updated',
            'touchpoint_logged' => 'Interaction Touchpoint Logged',
            'inspection_scheduled' => 'Site Inspection Scheduled',
            'inspection_confirmed' => 'Site Inspection Confirmed',
            'inspection_completed' => 'Site Inspection Completed',
            'inspection_cancelled' => 'Site Inspection Cancelled',
            'inspection_rescheduled' => 'Site Inspection Rescheduled',
            'ai_handover_executed' => 'AI Paused & Handed Over',
            'human_handover_requested' => 'Customer Requested Human Rep',
            'ai_resumed' => 'AI Automation Resumed',
            'lead_score_changed' => 'Lead Score Updated',
            'lead_stage_changed' => 'Pipeline Stage Moved',
            'deal_created' => 'Sales Deal Created',
            'deal_stage_changed' => 'Deal Stage Updated',
            'deal_won' => 'Deal Closed Won',
            'deal_lost' => 'Deal Marked Lost',
            'whatsapp_inbound' => 'Customer WhatsApp Message',
            'whatsapp_outbound' => 'Outbound WhatsApp Sent',
            default => str_replace('_', ' ', ucwords($type, '_')),
        };
    }

    /**
     * Map activity type to Lucide icon name.
     */
    protected function resolveActivityIcon(string $type): string
    {
        return match ($type) {
            'task_created' => 'CheckSquare',
            'task_completed' => 'CheckCircle2',
            'task_cancelled' => 'XCircle',
            'note_added' => 'FileText',
            'contact_created' => 'UserPlus',
            'contact_status_changed' => 'TrendingUp',
            'touchpoint_logged' => 'Clock',
            'inspection_scheduled', 'inspection_confirmed', 'inspection_completed', 'inspection_rescheduled' => 'Compass',
            'ai_handover_executed', 'human_handover_requested' => 'Users',
            'ai_resumed' => 'Sparkles',
            'lead_score_changed' => 'Flame',
            'lead_stage_changed' => 'Layers',
            'deal_created', 'deal_stage_changed', 'deal_won', 'deal_lost' => 'Handshake',
            'whatsapp_inbound', 'whatsapp_outbound' => 'MessageSquare',
            default => 'Activity',
        };
    }

    /**
     * Map activity type to Tailwind badge styling.
     */
    protected function resolveActivityBadge(string $type): string
    {
        return match ($type) {
            'task_completed', 'deal_won', 'inspection_completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'task_created', 'inspection_scheduled', 'inspection_confirmed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            'lead_score_changed' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            'ai_handover_executed', 'human_handover_requested' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            'deal_lost', 'task_cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 border-red-200 dark:border-red-800',
            'ai_resumed' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }
}
