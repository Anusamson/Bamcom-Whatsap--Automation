<?php

namespace App\Console\Commands;

use App\Enums\InspectionStatus;
use App\Enums\TaskStatus;
use App\Models\Inspection;
use App\Models\Task;
use App\Notifications\InspectionReminderNotification;
use App\Notifications\OverdueTaskNotification;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:check-reminders {--force : Disregard recent notification deduplication}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan CRM for upcoming inspections and overdue tasks to dispatch staff reminders';

    public function handle(NotificationDispatchService $dispatcher): int
    {
        $this->info('Starting CRM inspection and overdue task checks...');
        $force = (bool) $this->option('force');

        $inspectionsCount = $this->checkUpcomingInspections($dispatcher, $force);
        $tasksCount = $this->checkOverdueTasks($dispatcher, $force);

        $this->info("Completed reminder checks. Sent {$inspectionsCount} inspection reminders and {$tasksCount} overdue task alerts.");

        return self::SUCCESS;
    }

    /**
     * Check upcoming inspections scheduled within the next 24 hours.
     */
    protected function checkUpcomingInspections(NotificationDispatchService $dispatcher, bool $force): int
    {
        $upcoming = Inspection::whereIn('status', [
            InspectionStatus::Scheduled->value,
            InspectionStatus::Confirmed->value,
        ])
            ->whereNotNull('representative_id')
            ->whereDate('inspection_date', '<=', now()->addHours(24)->toDateString())
            ->whereDate('inspection_date', '>=', now()->toDateString())
            ->get();

        $sentCount = 0;

        foreach ($upcoming as $inspection) {
            // Check if reminder was already sent in the past 12 hours
            if (! $force) {
                $alreadySent = DB::table('notifications')
                    ->where('type', InspectionReminderNotification::class)
                    ->where('notifiable_id', $inspection->representative_id)
                    ->whereJsonContains('data->inspection_id', $inspection->id)
                    ->where('created_at', '>=', now()->subHours(12))
                    ->exists();

                if ($alreadySent) {
                    continue;
                }
            }

            $timing = $inspection->inspection_date->isToday() ? 'Today' : 'Tomorrow';
            $dispatcher->notifyInspectionReminder($inspection, $timing);
            $sentCount++;
        }

        return $sentCount;
    }

    /**
     * Check tasks that have passed due date and are still incomplete.
     */
    protected function checkOverdueTasks(NotificationDispatchService $dispatcher, bool $force): int
    {
        $overdue = Task::where('status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('assigned_user_id')
            ->where('due_at', '<', now())
            ->get();

        $sentCount = 0;

        foreach ($overdue as $task) {
            // Check if alert was already sent in past 24 hours
            if (! $force) {
                $alreadySent = DB::table('notifications')
                    ->where('type', OverdueTaskNotification::class)
                    ->where('notifiable_id', $task->assigned_user_id)
                    ->whereJsonContains('data->task_id', $task->id)
                    ->where('created_at', '>=', now()->subHours(24))
                    ->exists();

                if ($alreadySent) {
                    continue;
                }
            }

            $dispatcher->notifyTaskOverdue($task);
            $sentCount++;
        }

        return $sentCount;
    }
}
