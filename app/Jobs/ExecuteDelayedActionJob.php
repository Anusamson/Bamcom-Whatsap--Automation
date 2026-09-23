<?php

namespace App\Jobs;

use App\Enums\AutomationLogStatus;
use App\Enums\AutomationRunStatus;
use App\Models\AutomationRunLog;
use App\Services\Automation\ActionExecutor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued job for executing delayed automation actions.
 */
class ExecuteDelayedActionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $runLogId
    ) {}

    public function handle(ActionExecutor $executor): void
    {
        $log = AutomationRunLog::with(['run.workflow', 'run.subject', 'action'])->find($this->runLogId);
        if (! $log) {
            Log::warning("Delayed automation run log #{$this->runLogId} not found, skipping.");

            return;
        }

        $run = $log->run;
        $workflow = $run?->workflow;

        // Verify run is not cancelled or failed, and workflow is still active
        if (! $run || $run->status === AutomationRunStatus::Cancelled || ! $workflow || ! $workflow->is_active) {
            $log->update([
                'status' => AutomationLogStatus::Skipped,
                'error_message' => 'Workflow was deactivated or run was cancelled before delayed execution.',
            ]);

            return;
        }

        $action = $log->action;
        if (! $action) {
            $log->update([
                'status' => AutomationLogStatus::Failed,
                'error_message' => 'Associated action definition not found.',
            ]);

            return;
        }

        $log->update([
            'status' => AutomationLogStatus::Executing,
            'executed_at' => now(),
        ]);

        $result = $executor->execute($action, $run, $run->trigger_payload ?? []);

        if ($result['success']) {
            $log->update([
                'status' => AutomationLogStatus::Success,
                'output_payload' => $result['output'] ?? null,
            ]);
        } else {
            $log->update([
                'status' => AutomationLogStatus::Failed,
                'error_message' => $result['error'] ?? 'Execution failed without error detail',
            ]);
        }

        // Check if there are any remaining pending or delayed actions for this run
        $hasPending = $run->logs()
            ->whereIn('status', [AutomationLogStatus::Pending->value, AutomationLogStatus::Delayed->value, AutomationLogStatus::Executing->value])
            ->exists();

        if (! $hasPending) {
            $run->update([
                'status' => AutomationRunStatus::Completed,
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ExecuteDelayedActionJob failed for log #{$this->runLogId}: {$exception->getMessage()}");

        $log = AutomationRunLog::find($this->runLogId);
        if ($log) {
            $log->update([
                'status' => AutomationLogStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
