<?php

namespace App\Notifications;

use App\Enums\TaskPriority;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OverdueTaskNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $contact = $this->task->contact;
        $dueDate = $this->task->due_at ? $this->task->due_at->format('M j, Y') : 'Past due';
        $priority = $this->task->priority instanceof TaskPriority ? $this->task->priority->value : (string) ($this->task->priority ?? 'medium');

        return [
            'type' => 'overdue_task',
            'task_id' => $this->task->id,
            'title' => 'Overdue Task: '.$this->task->title,
            'message' => 'Task "'.$this->task->title.'" was due on '.$dueDate.' and remains incomplete.',
            'due_date' => $dueDate,
            'priority' => $priority,
            'contact_id' => $contact?->id,
            'contact_name' => $contact?->full_name,
            'url' => route('tasks.index'),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
