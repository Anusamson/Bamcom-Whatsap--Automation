<?php

namespace App\Services\Automation;

use App\Enums\AutomationLogStatus;
use App\Enums\AutomationRunStatus;
use App\Jobs\ExecuteDelayedActionJob;
use App\Models\AutomationAction;
use App\Models\AutomationRun;
use App\Models\AutomationRunLog;
use App\Models\AutomationTrigger;
use App\Models\AutomationWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Enterprise Event-Driven Automation Engine.
 *
 * Coordinates:
 * - Trigger evaluation
 * - Duplicate run prevention & concurrency locks
 * - Condition tree filtering
 * - Synchronous & queued delayed action execution
 * - Run auditing and status tracking
 */
class AutomationEngine
{
    public function __construct(
        protected TriggerEvaluator $triggerEvaluator,
        protected ActionExecutor $actionExecutor
    ) {}

    /**
     * Dispatch an event to all matching automation workflows.
     *
     * @param  array<string, mixed>  $context
     * @return Collection<int, AutomationRun>
     */
    public function dispatch(string $eventType, Model $subject, array $context = []): Collection
    {
        $runs = collect();

        // Find active workflows with active triggers matching this event type
        $workflows = AutomationWorkflow::query()
            ->active()
            ->with([
                'triggers' => fn ($q) => $q->active(),
                'conditions',
                'actions',
            ])
            ->get();

        foreach ($workflows as $workflow) {
            foreach ($workflow->triggers as $trigger) {
                if (! $this->triggerEvaluator->matchesTrigger($trigger, $eventType, $context)) {
                    continue;
                }

                $run = $this->processWorkflowRun($workflow, $trigger, $subject, $context, $eventType);
                if ($run) {
                    $runs->push($run);
                }
            }
        }

        return $runs;
    }

    /**
     * Execute a workflow manually for a subject.
     *
     * @param  array<string, mixed>  $context
     */
    public function executeWorkflowManually(AutomationWorkflow $workflow, Model $subject, array $context = []): ?AutomationRun
    {
        $workflow->loadMissing(['conditions', 'actions']);
        $trigger = $workflow->triggers()->first();

        return $this->processWorkflowRun($workflow, $trigger, $subject, $context, 'manual', ignoreDuplicateRules: true);
    }

    /**
     * Process a single workflow execution with concurrency lock and deduplication.
     *
     * @param  array<string, mixed>  $context
     */
    protected function processWorkflowRun(
        AutomationWorkflow $workflow,
        ?AutomationTrigger $trigger,
        Model $subject,
        array $context,
        string $eventType,
        bool $ignoreDuplicateRules = false
    ): ?AutomationRun {
        $subjectType = $subject->getMorphClass();
        $subjectId = $subject->getKey();

        // 1. Concurrency lock to prevent race conditions from duplicate simultaneous events
        $lockKey = sprintf('lock:automation:wf:%d:subj:%s:%s', $workflow->id, $subjectType, (string) $subjectId);
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            Log::info("Automation workflow #{$workflow->id} skipped: concurrent execution lock active for {$subjectType} #{$subjectId}");

            return null;
        }

        try {
            // 2. Duplicate prevention checks
            if (! $ignoreDuplicateRules && $this->isDuplicateRun($workflow, $subjectType, $subjectId)) {
                Log::info("Automation workflow #{$workflow->id} skipped: duplicate execution prevented for {$subjectType} #{$subjectId}");

                return null;
            }

            // 3. Condition tree evaluation
            if (! $this->triggerEvaluator->evaluateConditions($workflow->conditions, $subject, $context)) {
                return null;
            }

            // 4. Generate unique idempotency key
            $idempotencyKey = sha1(sprintf(
                'wf:%d:trig:%s:subj:%s:%s:event:%s:time:%s',
                $workflow->id,
                $trigger?->id ?? 'manual',
                $subjectType,
                (string) $subjectId,
                $eventType,
                now()->format('Y-m-d-H-i')
            ));

            // 5. Initialize Automation Run
            $run = AutomationRun::create([
                'workflow_id' => $workflow->id,
                'trigger_id' => $trigger?->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'idempotency_key' => $idempotencyKey,
                'status' => AutomationRunStatus::Running,
                'started_at' => now(),
                'trigger_payload' => $context,
            ]);

            // 6. Execute actions in configured order
            $hasDelayedAction = false;

            foreach ($workflow->actions as $action) {
                if ($action->hasDelay()) {
                    $hasDelayedAction = true;
                    $this->scheduleDelayedAction($action, $run);
                } else {
                    $this->executeImmediateAction($action, $run, $context);
                }
            }

            // If all actions were immediate, mark run as completed
            if (! $hasDelayedAction) {
                $run->update([
                    'status' => AutomationRunStatus::Completed,
                    'completed_at' => now(),
                ]);
            }

            return $run->fresh(['logs']);
        } finally {
            $lock->release();
        }
    }

    /**
     * Check if a workflow execution would violate duplicate rules.
     */
    protected function isDuplicateRun(AutomationWorkflow $workflow, string $subjectType, int|string $subjectId): bool
    {
        // Rule A: Workflow does not allow multiple runs for the same subject
        if (! $workflow->allow_multiple_runs_per_subject) {
            $hasPriorRun = AutomationRun::query()
                ->where('workflow_id', $workflow->id)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->whereNotIn('status', [AutomationRunStatus::Failed->value, AutomationRunStatus::Cancelled->value])
                ->exists();

            if ($hasPriorRun) {
                return true;
            }
        }

        // Rule B: Cooldown window in seconds
        if ($workflow->prevent_duplicate_window_seconds && $workflow->prevent_duplicate_window_seconds > 0) {
            $threshold = Carbon::now()->subSeconds($workflow->prevent_duplicate_window_seconds);

            $hasRecentRun = AutomationRun::query()
                ->where('workflow_id', $workflow->id)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->where('created_at', '>=', $threshold)
                ->exists();

            if ($hasRecentRun) {
                return true;
            }
        }

        return false;
    }

    /**
     * Execute an immediate automation action.
     *
     * @param  array<string, mixed>  $context
     */
    protected function executeImmediateAction(AutomationAction $action, AutomationRun $run, array $context): AutomationRunLog
    {
        $log = AutomationRunLog::create([
            'run_id' => $run->id,
            'action_id' => $action->id,
            'action_type' => $action->action_type,
            'status' => AutomationLogStatus::Executing,
            'input_payload' => $action->action_config,
            'executed_at' => now(),
        ]);

        $result = $this->actionExecutor->execute($action, $run, $context);

        if ($result['success']) {
            $log->update([
                'status' => AutomationLogStatus::Success,
                'output_payload' => $result['output'] ?? null,
            ]);
        } else {
            $log->update([
                'status' => AutomationLogStatus::Failed,
                'error_message' => $result['error'] ?? 'Execution failed without error message',
            ]);
        }

        return $log;
    }

    /**
     * Schedule a delayed automation action using queue.
     */
    protected function scheduleDelayedAction(AutomationAction $action, AutomationRun $run): AutomationRunLog
    {
        $scheduledAt = Carbon::now()->addSeconds($action->delay_seconds);

        $log = AutomationRunLog::create([
            'run_id' => $run->id,
            'action_id' => $action->id,
            'action_type' => $action->action_type,
            'status' => AutomationLogStatus::Delayed,
            'input_payload' => $action->action_config,
            'scheduled_at' => $scheduledAt,
        ]);

        // Dispatch queued job with delay
        ExecuteDelayedActionJob::dispatch($log->id)->delay($action->delay_seconds);

        return $log;
    }
}
