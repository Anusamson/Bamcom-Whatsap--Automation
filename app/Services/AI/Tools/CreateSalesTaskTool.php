<?php

namespace App\Services\AI\Tools;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Models\Activity;
use App\Models\Contact;
use App\Services\Task\TaskService;
use Illuminate\Support\Carbon;

/**
 * Controlled Tool: createSalesTask
 *
 * Creates a sales follow-up task or reminder in the CRM for human sales executives.
 */
class CreateSalesTaskTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'createSalesTask';
    }

    public function getDescription(): string
    {
        return 'Create a follow-up task or reminder in the CRM for the sales team regarding this client.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Title of the follow-up task (e.g., "Send survey plan for Grace Haven Plot 12").',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Detailed context or action instructions for the sales representative.',
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'Due date in YYYY-MM-DD format (defaults to tomorrow if omitted).',
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['low', 'normal', 'high', 'urgent'],
                    'description' => 'Task urgency level (default: "normal").',
                ],
                'assigned_user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional user ID of specific sales rep to assign.',
                ],
            ],
            'required' => ['title'],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $title = trim((string) ($arguments['title'] ?? ''));

        if (empty($title)) {
            return [
                'success' => false,
                'error' => 'A task title is required.',
            ];
        }

        $contact = $context['contact'] ?? $context['conversation']?->contact;
        $lead = $contact instanceof Contact ? $contact->leads()->latest()->first() : null;

        $dueDate = ! empty($arguments['due_date'])
            ? trim((string) $arguments['due_date'])
            : now()->addDay()->toDateString();

        $priority = ! empty($arguments['priority'])
            ? strtolower((string) $arguments['priority'])
            : 'normal';

        $assignedUserId = ! empty($arguments['assigned_user_id'])
            ? (int) $arguments['assigned_user_id']
            : ($context['conversation']?->assigned_user_id ?? $lead?->assigned_user_id);

        $taskPriority = match ($priority) {
            'urgent' => TaskPriority::Urgent,
            'high' => TaskPriority::High,
            'low' => TaskPriority::Low,
            default => TaskPriority::Medium,
        };

        $realTask = app(TaskService::class)->createTask([
            'title' => $title,
            'description' => $arguments['description'] ?? null,
            'contact_id' => $contact?->id,
            'lead_id' => $lead?->id,
            'assigned_user_id' => $assignedUserId,
            'due_at' => Carbon::parse($dueDate),
            'priority' => $taskPriority,
            'type' => TaskType::FollowUp,
        ]);

        $activity = Activity::create([
            'contact_id' => $contact?->id,
            'lead_id' => $lead?->id,
            'user_id' => $assignedUserId ?? auth()->id(),
            'activity_type' => 'task',
            'description' => $title.(! empty($arguments['description']) ? ': '.$arguments['description'] : ''),
            'properties' => [
                'task_id' => $realTask->id,
                'title' => $title,
                'task_description' => $arguments['description'] ?? null,
                'due_date' => $dueDate,
                'priority' => $priority,
                'assigned_user_id' => $assignedUserId,
                'created_by_ai' => true,
                'status' => 'pending',
            ],
        ]);

        return [
            'success' => true,
            'task_id' => $realTask->id,
            'activity_id' => $activity->id,
            'title' => $title,
            'due_date' => $dueDate,
            'priority' => $priority,
            'message' => "Sales task '{$title}' successfully created and assigned to the sales team.",
        ];
    }
}
