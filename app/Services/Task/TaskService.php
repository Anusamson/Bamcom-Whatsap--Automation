<?php

namespace App\Services\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity\ActivityRecorderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Service for managing CRM Sales Tasks, Follow-ups, and Reminders.
 */
class TaskService
{
    public function __construct(
        protected ActivityRecorderService $activityRecorder
    ) {}

    /**
     * Retrieve tasks categorized by view with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Task>
     */
    public function getTasks(string $view = 'today', array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Task::query()
            ->with(['contact', 'lead', 'deal', 'assignedUser', 'creator']);

        // View categorization
        match ($view) {
            'today' => $query->today()->orderBy('due_at'),
            'overdue' => $query->overdue()->orderBy('due_at'),
            'upcoming' => $query->upcoming()->orderBy('due_at'),
            'completed' => $query->completed()->latest('completed_at'),
            'all' => $query->latest('due_at'),
            default => $query->today()->orderBy('due_at'),
        };

        // Filters
        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        if (! empty($filters['priority'])) {
            $query->priority($filters['priority']);
        }

        if (! empty($filters['type'])) {
            $query->type($filters['type']);
        }

        if (! empty($filters['assigned_user_id'])) {
            $query->assignedTo((int) $filters['assigned_user_id']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', (int) $filters['contact_id']);
        }

        if (! empty($filters['lead_id'])) {
            $query->where('lead_id', (int) $filters['lead_id']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Compute task metrics for badge counters and dashboard ribbon.
     *
     * @return array<string, int>
     */
    public function getMetrics(?int $userId = null): array
    {
        $base = Task::query();

        if ($userId) {
            $base->where('assigned_user_id', $userId);
        }

        return [
            'today' => (clone $base)->today()->count(),
            'overdue' => (clone $base)->overdue()->count(),
            'upcoming' => (clone $base)->upcoming()->count(),
            'completed' => (clone $base)->completed()->count(),
            'urgent' => (clone $base)->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
                ->where('priority', TaskPriority::Urgent->value)
                ->count(),
            'total_active' => (clone $base)->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])->count(),
        ];
    }

    /**
     * Create a new sales task and log CRM activity.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTask(array $data, ?User $creator = null): Task
    {
        $creatorId = $creator?->id ?? auth()->id();

        // Resolve contact from lead if missing
        $contactId = $data['contact_id'] ?? null;
        if (! $contactId && ! empty($data['lead_id'])) {
            $contactId = Lead::where('id', $data['lead_id'])->value('contact_id');
        }

        $dueAt = isset($data['due_at']) ? Carbon::parse($data['due_at']) : now()->addDay();

        $priority = ($data['priority'] ?? null) instanceof TaskPriority
            ? $data['priority']
            : (isset($data['priority']) ? TaskPriority::tryFrom((string) $data['priority']) : null) ?? TaskPriority::Medium;

        $type = ($data['type'] ?? null) instanceof TaskType
            ? $data['type']
            : (isset($data['type']) ? TaskType::tryFrom((string) $data['type']) : null) ?? TaskType::FollowUp;

        $status = ($data['status'] ?? null) instanceof TaskStatus
            ? $data['status']
            : (isset($data['status']) ? TaskStatus::tryFrom((string) $data['status']) : null) ?? TaskStatus::Pending;

        $task = Task::create([
            'title' => trim((string) $data['title']),
            'description' => ! empty($data['description']) ? trim((string) $data['description']) : null,
            'contact_id' => $contactId,
            'lead_id' => $data['lead_id'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'created_by_id' => $creatorId,
            'due_at' => $dueAt,
            'priority' => $priority,
            'status' => $status,
            'type' => $type,
        ]);

        // Auto-record CRM activity
        $this->activityRecorder->recordTaskCreated($task, $creator);

        return $task;
    }

    /**
     * Update an existing task.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTask(Task $task, array $data, ?User $actor = null): Task
    {
        $oldStatus = $task->status;

        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = trim((string) $data['title']);
        }

        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'] ? trim((string) $data['description']) : null;
        }

        if (array_key_exists('contact_id', $data)) {
            $updateData['contact_id'] = $data['contact_id'];
        }

        if (array_key_exists('lead_id', $data)) {
            $updateData['lead_id'] = $data['lead_id'];
        }

        if (array_key_exists('deal_id', $data)) {
            $updateData['deal_id'] = $data['deal_id'];
        }

        if (array_key_exists('assigned_user_id', $data)) {
            $updateData['assigned_user_id'] = $data['assigned_user_id'];
        }

        if (isset($data['due_at'])) {
            $updateData['due_at'] = Carbon::parse($data['due_at']);
        }

        if (isset($data['priority'])) {
            $updateData['priority'] = $data['priority'] instanceof TaskPriority
                ? $data['priority']
                : TaskPriority::tryFrom($data['priority']) ?? $task->priority;
        }

        if (isset($data['type'])) {
            $updateData['type'] = $data['type'] instanceof TaskType
                ? $data['type']
                : TaskType::tryFrom($data['type']) ?? $task->type;
        }

        if (isset($data['status'])) {
            $newStatus = $data['status'] instanceof TaskStatus
                ? $data['status']
                : TaskStatus::tryFrom($data['status']) ?? $task->status;

            $updateData['status'] = $newStatus;

            if ($newStatus === TaskStatus::Completed && $oldStatus !== TaskStatus::Completed) {
                $updateData['completed_at'] = now();
            } elseif ($newStatus !== TaskStatus::Completed) {
                $updateData['completed_at'] = null;
            }
        }

        $task->update($updateData);

        // Record activity if completed or cancelled
        if (isset($updateData['status']) && $updateData['status'] === TaskStatus::Completed && $oldStatus !== TaskStatus::Completed) {
            $this->activityRecorder->recordTaskCompleted($task, $actor);
        } elseif (isset($updateData['status']) && $updateData['status'] === TaskStatus::Cancelled && $oldStatus !== TaskStatus::Cancelled) {
            $this->activityRecorder->recordTaskCancelled($task, $actor);
        }

        return $task;
    }

    /**
     * Mark a task as completed.
     */
    public function completeTask(Task $task, ?User $actor = null): Task
    {
        $task->update([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->activityRecorder->recordTaskCompleted($task, $actor);

        return $task;
    }

    /**
     * Reopen a completed/cancelled task.
     */
    public function reopenTask(Task $task, ?User $actor = null): Task
    {
        $task->update([
            'status' => TaskStatus::Pending,
            'completed_at' => null,
        ]);

        return $task;
    }

    /**
     * Cancel a task.
     */
    public function cancelTask(Task $task, ?User $actor = null, ?string $reason = null): Task
    {
        $task->update([
            'status' => TaskStatus::Cancelled,
        ]);

        $this->activityRecorder->recordTaskCancelled($task, $actor, $reason);

        return $task;
    }

    /**
     * Delete / archive a task.
     */
    public function deleteTask(Task $task): bool
    {
        return (bool) $task->delete();
    }
}
