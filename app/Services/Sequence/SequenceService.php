<?php

namespace App\Services\Sequence;

use App\Enums\SequenceEnrollmentStatus;
use App\Enums\SequenceStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Enums\UserRole;
use App\Jobs\ExecuteSequenceStepJob;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Models\SequenceStepLog;
use App\Models\User;
use App\Notifications\AutomationNotification;
use App\Services\Contact\ContactService;
use App\Services\Conversation\ConversationService;
use App\Services\Lead\PipelineService;
use App\Services\Task\TaskService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SequenceService
{
    public function __construct(
        protected SequenceStepVerifier $verifier,
        protected WhatsAppMessageService $whatsAppService,
        protected TaskService $taskService,
        protected PipelineService $pipelineService,
        protected ContactService $contactService,
        protected ConversationService $conversationService
    ) {}

    /**
     * Create a new follow-up sequence.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSequence(array $data, ?User $creator = null): FollowUpSequence
    {
        return DB::transaction(function () use ($data, $creator): FollowUpSequence {
            $status = isset($data['status'])
                ? ($data['status'] instanceof SequenceStatus ? $data['status'] : SequenceStatus::from((string) $data['status']))
                : SequenceStatus::Draft;

            $sequence = FollowUpSequence::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $status,
                'trigger_type' => $data['trigger_type'] ?? 'manual',
                'trigger_config' => $data['trigger_config'] ?? null,
                'exit_on_deal_won' => (bool) ($data['exit_on_deal_won'] ?? true),
                'exit_on_reply' => (bool) ($data['exit_on_reply'] ?? false),
                'created_by_user_id' => $creator?->id,
            ]);

            if (! empty($data['steps']) && is_array($data['steps'])) {
                foreach ($data['steps'] as $index => $stepData) {
                    $stepData['step_number'] = $stepData['step_number'] ?? ($index + 1);
                    $this->addStep($sequence, $stepData);
                }
            }

            return $sequence->load('steps');
        });
    }

    /**
     * Update an existing sequence.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSequence(FollowUpSequence $sequence, array $data): FollowUpSequence
    {
        return DB::transaction(function () use ($sequence, $data): FollowUpSequence {
            if (isset($data['status']) && ! ($data['status'] instanceof SequenceStatus)) {
                $data['status'] = SequenceStatus::from((string) $data['status']);
            }

            $sequence->update(array_filter([
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? null,
                'trigger_type' => $data['trigger_type'] ?? null,
                'trigger_config' => $data['trigger_config'] ?? null,
                'exit_on_deal_won' => isset($data['exit_on_deal_won']) ? (bool) $data['exit_on_deal_won'] : null,
                'exit_on_reply' => isset($data['exit_on_reply']) ? (bool) $data['exit_on_reply'] : null,
            ], fn ($val) => ! is_null($val)));

            if (isset($data['steps']) && is_array($data['steps'])) {
                // Remove deleted steps if step IDs are specified
                $keptStepIds = array_filter(array_column($data['steps'], 'id'));
                if (! empty($keptStepIds)) {
                    $sequence->steps()->whereNotIn('id', $keptStepIds)->delete();
                }

                foreach ($data['steps'] as $index => $stepData) {
                    $stepData['step_number'] = $stepData['step_number'] ?? ($index + 1);
                    if (! empty($stepData['id'])) {
                        $step = SequenceStep::find($stepData['id']);
                        if ($step && $step->sequence_id === $sequence->id) {
                            $this->updateStep($step, $stepData);
                        }
                    } else {
                        $this->addStep($sequence, $stepData);
                    }
                }
            }

            return $sequence->fresh(['steps']);
        });
    }

    /**
     * Add a step to a sequence.
     *
     * @param  array<string, mixed>  $stepData
     */
    public function addStep(FollowUpSequence $sequence, array $stepData): SequenceStep
    {
        $stepNumber = $stepData['step_number'] ?? (($sequence->steps()->max('step_number') ?? 0) + 1);

        return SequenceStep::create([
            'sequence_id' => $sequence->id,
            'step_number' => $stepNumber,
            'name' => $stepData['name'] ?? "Step {$stepNumber}",
            'delay_minutes' => (int) ($stepData['delay_minutes'] ?? 0),
            'delay_type' => $stepData['delay_type'] ?? 'minutes',
            'whatsapp_config' => $stepData['whatsapp_config'] ?? null,
            'task_config' => $stepData['task_config'] ?? null,
            'stage_change_config' => $stepData['stage_change_config'] ?? null,
            'tag_config' => $stepData['tag_config'] ?? null,
            'assignment_config' => $stepData['assignment_config'] ?? null,
            'notification_config' => $stepData['notification_config'] ?? null,
            'applicability_rules' => $stepData['applicability_rules'] ?? null,
        ]);
    }

    /**
     * Update a step.
     *
     * @param  array<string, mixed>  $stepData
     */
    public function updateStep(SequenceStep $step, array $stepData): SequenceStep
    {
        $step->update([
            'step_number' => $stepData['step_number'] ?? $step->step_number,
            'name' => $stepData['name'] ?? $step->name,
            'delay_minutes' => isset($stepData['delay_minutes']) ? (int) $stepData['delay_minutes'] : $step->delay_minutes,
            'delay_type' => $stepData['delay_type'] ?? $step->delay_type,
            'whatsapp_config' => $stepData['whatsapp_config'] ?? $step->whatsapp_config,
            'task_config' => $stepData['task_config'] ?? $step->task_config,
            'stage_change_config' => $stepData['stage_change_config'] ?? $step->stage_change_config,
            'tag_config' => $stepData['tag_config'] ?? $step->tag_config,
            'assignment_config' => $stepData['assignment_config'] ?? $step->assignment_config,
            'notification_config' => $stepData['notification_config'] ?? $step->notification_config,
            'applicability_rules' => $stepData['applicability_rules'] ?? $step->applicability_rules,
        ]);

        return $step;
    }

    /**
     * Delete a step from a sequence.
     */
    public function deleteStep(SequenceStep $step): bool
    {
        $sequenceId = $step->sequence_id;
        $deleted = $step->delete();

        // Re-number remaining steps
        $steps = SequenceStep::where('sequence_id', $sequenceId)->orderBy('step_number')->get();
        foreach ($steps as $index => $s) {
            $s->update(['step_number' => $index + 1]);
        }

        return $deleted;
    }

    /**
     * Delete a sequence and cancel active enrollments.
     */
    public function deleteSequence(FollowUpSequence $sequence): bool
    {
        return DB::transaction(function () use ($sequence): bool {
            $sequence->enrollments()
                ->where('status', SequenceEnrollmentStatus::Active->value)
                ->update([
                    'status' => SequenceEnrollmentStatus::Cancelled->value,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Sequence was deleted',
                ]);

            return (bool) $sequence->delete();
        });
    }

    /**
     * Toggle sequence active/paused status.
     */
    public function toggleStatus(FollowUpSequence $sequence): FollowUpSequence
    {
        $newStatus = $sequence->status === SequenceStatus::Active
            ? SequenceStatus::Paused
            : SequenceStatus::Active;

        $sequence->update(['status' => $newStatus]);

        return $sequence;
    }

    /**
     * Enroll a contact into a sequence.
     *
     * @throws InvalidArgumentException
     */
    public function enroll(
        Contact $contact,
        FollowUpSequence $sequence,
        ?Lead $lead = null,
        ?User $enrolledBy = null
    ): SequenceEnrollment {
        if ($sequence->status !== SequenceStatus::Active) {
            throw new InvalidArgumentException("Cannot enroll into sequence that is not active (current status: {$sequence->status->label()}).");
        }

        if ($contact->has_opted_out) {
            throw new InvalidArgumentException('Cannot enroll contact who has opted out of communications.');
        }

        if ($contact->trashed()) {
            throw new InvalidArgumentException('Cannot enroll a deleted contact.');
        }

        // Check if contact already has an active enrollment in this sequence
        $existingEnrollment = SequenceEnrollment::query()
            ->where('sequence_id', $sequence->id)
            ->where('contact_id', $contact->id)
            ->where('status', SequenceEnrollmentStatus::Active->value)
            ->first();

        if ($existingEnrollment) {
            return $existingEnrollment;
        }

        $lead = $lead ?? $contact->leads()->latest()->first();

        $enrollment = SequenceEnrollment::create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'lead_id' => $lead?->id,
            'enrolled_by_user_id' => $enrolledBy?->id,
            'status' => SequenceEnrollmentStatus::Active,
            'current_step_number' => 0,
            'enrolled_at' => now(),
        ]);

        $firstStep = $sequence->steps()->orderBy('step_number', 'asc')->first();

        if ($firstStep) {
            $this->scheduleStepExecution($enrollment, $firstStep);
        } else {
            $enrollment->update([
                'status' => SequenceEnrollmentStatus::Completed,
                'completed_at' => now(),
            ]);
        }

        return $enrollment;
    }

    /**
     * Unenroll a contact from an active sequence enrollment.
     */
    public function unenroll(
        SequenceEnrollment $enrollment,
        string $reason = 'Manual unenrollment',
        ?User $actor = null
    ): SequenceEnrollment {
        if (! $enrollment->isActive()) {
            return $enrollment;
        }

        $enrollment->update([
            'status' => SequenceEnrollmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'next_step_id' => null,
            'next_step_due_at' => null,
        ]);

        return $enrollment;
    }

    /**
     * Unenroll a contact from all or a specific sequence.
     */
    public function unenrollContact(Contact $contact, ?FollowUpSequence $sequence = null, string $reason = 'Manual unenrollment'): int
    {
        $query = SequenceEnrollment::query()
            ->where('contact_id', $contact->id)
            ->where('status', SequenceEnrollmentStatus::Active->value);

        if ($sequence) {
            $query->where('sequence_id', $sequence->id);
        }

        return $query->update([
            'status' => SequenceEnrollmentStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'next_step_id' => null,
            'next_step_due_at' => null,
        ]);
    }

    /**
     * Pause an enrollment.
     */
    public function pauseEnrollment(SequenceEnrollment $enrollment): SequenceEnrollment
    {
        if ($enrollment->isActive()) {
            $enrollment->update(['status' => SequenceEnrollmentStatus::Paused]);
        }

        return $enrollment;
    }

    /**
     * Resume a paused enrollment.
     */
    public function resumeEnrollment(SequenceEnrollment $enrollment): SequenceEnrollment
    {
        if ($enrollment->status === SequenceEnrollmentStatus::Paused) {
            $enrollment->update(['status' => SequenceEnrollmentStatus::Active]);

            if ($enrollment->next_step_id) {
                $step = SequenceStep::find($enrollment->next_step_id);
                if ($step) {
                    $this->scheduleStepExecution($enrollment, $step);
                }
            }
        }

        return $enrollment;
    }

    /**
     * Schedule a step for execution.
     */
    public function scheduleStepExecution(SequenceEnrollment $enrollment, SequenceStep $step): void
    {
        $delayMinutes = max(0, $step->delay_minutes);
        $dueAt = now()->addMinutes($delayMinutes);

        $enrollment->update([
            'next_step_id' => $step->id,
            'next_step_due_at' => $dueAt,
        ]);

        if ($delayMinutes > 0) {
            ExecuteSequenceStepJob::dispatch($enrollment->id, $step->id)->delay($dueAt);
        } else {
            ExecuteSequenceStepJob::dispatch($enrollment->id, $step->id);
        }
    }

    /**
     * Execute a sequence step with the 5 pre-execution guardrails.
     */
    public function executeStep(SequenceEnrollment $enrollment, SequenceStep $step): void
    {
        // 1. Basic status check
        if ($enrollment->status !== SequenceEnrollmentStatus::Active) {
            Log::info("SequenceService: Enrollment #{$enrollment->id} is not active ({$enrollment->status->value}). Skipping step execution.");

            return;
        }

        if ($enrollment->sequence->status !== SequenceStatus::Active) {
            Log::info("SequenceService: Sequence #{$enrollment->sequence_id} is not active ({$enrollment->sequence->status->value}). Skipping step execution.");

            return;
        }

        // 2. Run the 5 guardrails
        $verification = $this->verifier->verifyAll($enrollment, $step);

        if (! $verification['can_execute']) {
            if ($verification['should_cancel_sequence']) {
                // Guardrail failed terminally -> cancel sequence enrollment
                $enrollment->update([
                    'status' => SequenceEnrollmentStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $verification['reason'],
                    'next_step_id' => null,
                    'next_step_due_at' => null,
                ]);

                SequenceStepLog::create([
                    'sequence_enrollment_id' => $enrollment->id,
                    'sequence_step_id' => $step->id,
                    'status' => 'skipped',
                    'skip_reason' => $verification['reason'],
                    'verification_results' => $verification['checks'],
                    'executed_at' => now(),
                ]);

                Log::info("SequenceService: Enrollment #{$enrollment->id} cancelled by guardrail: {$verification['reason']}");

                return;
            }

            // Non-terminal failure (e.g. message not applicable right now) -> skip step and advance
            SequenceStepLog::create([
                'sequence_enrollment_id' => $enrollment->id,
                'sequence_step_id' => $step->id,
                'status' => 'skipped',
                'skip_reason' => $verification['reason'],
                'verification_results' => $verification['checks'],
                'executed_at' => now(),
            ]);

            Log::info("SequenceService: Step #{$step->id} skipped for Enrollment #{$enrollment->id}: {$verification['reason']}. Advancing to next step.");

            $this->advanceToNextStep($enrollment, $step);

            return;
        }

        // 3. Execute step actions
        try {
            $actionsSummary = $this->executeStepActions($enrollment, $step);

            SequenceStepLog::create([
                'sequence_enrollment_id' => $enrollment->id,
                'sequence_step_id' => $step->id,
                'status' => 'completed',
                'actions_summary' => $actionsSummary,
                'verification_results' => $verification['checks'],
                'executed_at' => now(),
            ]);

            $enrollment->update([
                'last_executed_step_id' => $step->id,
                'current_step_number' => $step->step_number,
            ]);

            $this->advanceToNextStep($enrollment, $step);
        } catch (Exception $e) {
            Log::error("SequenceService: Error executing Step #{$step->id} for Enrollment #{$enrollment->id}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            SequenceStepLog::create([
                'sequence_enrollment_id' => $enrollment->id,
                'sequence_step_id' => $step->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'verification_results' => $verification['checks'],
                'executed_at' => now(),
            ]);
        }
    }

    /**
     * Advance enrollment to the next step or mark completed.
     */
    protected function advanceToNextStep(SequenceEnrollment $enrollment, SequenceStep $currentStep): void
    {
        $nextStep = $enrollment->sequence->steps()
            ->where('step_number', '>', $currentStep->step_number)
            ->orderBy('step_number', 'asc')
            ->first();

        if ($nextStep) {
            $this->scheduleStepExecution($enrollment, $nextStep);
        } else {
            $enrollment->update([
                'status' => SequenceEnrollmentStatus::Completed,
                'completed_at' => now(),
                'next_step_id' => null,
                'next_step_due_at' => null,
            ]);
        }
    }

    /**
     * Execute all configured actions on a sequence step:
     * - WhatsApp message/template
     * - Task
     * - Stage change
     * - Tag
     * - Assignment
     * - Notification
     *
     * @return array<string, mixed>
     */
    public function executeStepActions(SequenceEnrollment $enrollment, SequenceStep $step): array
    {
        $summary = [];
        $contact = $enrollment->contact;
        $lead = $enrollment->lead;

        // 1. WhatsApp Message / Template
        if ($step->hasWhatsApp()) {
            $summary['whatsapp'] = $this->executeWhatsAppAction($step->whatsapp_config ?? [], $contact, $lead);
        }

        // 2. Task
        if ($step->hasTask()) {
            $summary['task'] = $this->executeTaskAction($step->task_config ?? [], $contact, $lead);
        }

        // 3. Stage Change
        if ($step->hasStageChange()) {
            $summary['stage_change'] = $this->executeStageChangeAction($step->stage_change_config ?? [], $lead);
        }

        // 4. Tag
        if ($step->hasTagChange()) {
            $summary['tag'] = $this->executeTagAction($step->tag_config ?? [], $contact, $lead);
        }

        // 5. Assignment
        if ($step->hasAssignment()) {
            $summary['assignment'] = $this->executeAssignmentAction($step->assignment_config ?? [], $contact, $lead);
        }

        // 6. Notification
        if ($step->hasNotification()) {
            $summary['notification'] = $this->executeNotificationAction($step->notification_config ?? [], $contact, $lead);
        }

        return $summary;
    }

    /**
     * Execute WhatsApp action (message or template).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeWhatsAppAction(array $config, Contact $contact, ?Lead $lead): array
    {
        $phone = $config['phone'] ?? $contact->phone;
        if (empty($phone)) {
            throw new Exception('No phone number available to send WhatsApp message.');
        }

        if (! empty($config['template_name'])) {
            $params = (array) ($config['parameters'] ?? $config['params'] ?? []);
            $interpolatedParams = array_map(
                fn ($p) => is_string($p) ? $this->interpolateTokens($p, $contact, $lead) : $p,
                $params
            );

            return $this->whatsAppService->sendTemplateMessage(
                to: $phone,
                templateName: (string) $config['template_name'],
                bodyParameters: $interpolatedParams,
                languageCode: $config['language'] ?? 'en_US',
                contact: $contact
            );
        }

        $rawBody = (string) ($config['message'] ?? $config['body'] ?? 'Hello from Bamcom Real Estate');
        $body = $this->interpolateTokens($rawBody, $contact, $lead);

        return $this->whatsAppService->sendTextMessage(
            to: $phone,
            body: $body,
            contact: $contact
        );
    }

    /**
     * Execute task creation action.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeTaskAction(array $config, Contact $contact, ?Lead $lead): array
    {
        $rawTitle = (string) ($config['title'] ?? 'Follow-up Task');
        $title = $this->interpolateTokens($rawTitle, $contact, $lead);

        $description = isset($config['description'])
            ? $this->interpolateTokens((string) $config['description'], $contact, $lead)
            : null;

        $dueAt = now()->addDay();
        if (isset($config['due_in_hours'])) {
            $dueAt = now()->addHours((int) $config['due_in_hours']);
        } elseif (isset($config['due_in_days'])) {
            $dueAt = now()->addDays((int) $config['due_in_days']);
        } elseif (! empty($config['due_at'])) {
            $dueAt = Carbon::parse($config['due_at']);
        }

        $assignedUserId = $config['assigned_user_id'] ?? null;
        if (empty($assignedUserId) && $contact->assigned_user_id) {
            $assignedUserId = $contact->assigned_user_id;
        }

        $priority = $config['priority'] ?? TaskPriority::Medium->value;
        $type = $config['type'] ?? TaskType::FollowUp->value;

        $task = $this->taskService->createTask([
            'title' => $title,
            'description' => $description,
            'contact_id' => $contact->id,
            'lead_id' => $lead?->id,
            'assigned_user_id' => $assignedUserId,
            'due_at' => $dueAt,
            'priority' => $priority,
            'type' => $type,
        ]);

        return [
            'task_id' => $task->id,
            'title' => $task->title,
            'due_at' => $task->due_at->toIso8601String(),
        ];
    }

    /**
     * Execute stage change action.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeStageChangeAction(array $config, ?Lead $lead): array
    {
        if (! $lead) {
            throw new Exception('Cannot change pipeline stage: no associated lead.');
        }

        $targetStage = null;
        if (! empty($config['stage_id']) || ! empty($config['pipeline_stage_id'])) {
            $stageId = (int) ($config['stage_id'] ?? $config['pipeline_stage_id']);
            $targetStage = PipelineStage::find($stageId);
        } elseif (! empty($config['stage_name'])) {
            $targetStage = PipelineStage::where('name', $config['stage_name'])->first();
        }

        if (! $targetStage) {
            throw new Exception('Target pipeline stage not found for sequence stage change action.');
        }

        $updatedLead = $this->pipelineService->moveLeadStage($lead, $targetStage);

        return [
            'lead_id' => $updatedLead->id,
            'new_stage_id' => $targetStage->id,
            'new_stage_name' => $targetStage->name,
        ];
    }

    /**
     * Execute tag action (add/remove tags).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeTagAction(array $config, Contact $contact, ?Lead $lead): array
    {
        $attached = [];
        $detached = [];

        // Add tags
        $addTags = (array) ($config['add_tags'] ?? $config['tag'] ?? $config['tags'] ?? []);
        if (is_string($addTags)) {
            $addTags = [$addTags];
        }

        foreach ($addTags as $name) {
            if (empty(trim((string) $name))) {
                continue;
            }
            $cleanName = trim((string) $name);
            $contact->attachTag($cleanName);
            $attached[] = $cleanName;
        }

        // Remove tags
        $removeTags = (array) ($config['remove_tags'] ?? []);
        if (is_string($removeTags)) {
            $removeTags = [$removeTags];
        }

        foreach ($removeTags as $name) {
            if (empty(trim((string) $name))) {
                continue;
            }
            $cleanName = trim((string) $name);
            $contact->detachTag($cleanName);
            $detached[] = $cleanName;
        }

        return [
            'attached_tags' => $attached,
            'detached_tags' => $detached,
        ];
    }

    /**
     * Execute agent assignment action.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeAssignmentAction(array $config, Contact $contact, ?Lead $lead): array
    {
        $targetUser = null;

        if (! empty($config['user_id']) || ! empty($config['agent_id'])) {
            $userId = (int) ($config['user_id'] ?? $config['agent_id']);
            $targetUser = User::find($userId);
        } else {
            // Pick next active sales rep
            $targetUser = User::query()
                ->where('status', 'active')
                ->whereIn('role', [UserRole::SalesExecutive->value, UserRole::RelationshipManager->value, UserRole::SuperAdmin->value])
                ->inRandomOrder()
                ->first();
        }

        if (! $targetUser) {
            throw new Exception('No active representative found for sequence assignment.');
        }

        $contact->update(['assigned_user_id' => $targetUser->id]);

        if ($lead) {
            $lead->update(['assigned_user_id' => $targetUser->id]);
        }

        // Also assign active conversation
        $conversation = Conversation::where('contact_id', $contact->id)->latest()->first();
        if ($conversation) {
            $this->conversationService->assignUser($conversation, $targetUser);
        }

        return [
            'assigned_user_id' => $targetUser->id,
            'assigned_user_name' => $targetUser->name,
        ];
    }

    /**
     * Execute notification action.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeNotificationAction(array $config, Contact $contact, ?Lead $lead): array
    {
        $rawTitle = (string) ($config['title'] ?? 'Sequence Follow-up Notification');
        $rawMessage = (string) ($config['message'] ?? "Follow-up notification for contact {$contact->full_name}");

        $title = $this->interpolateTokens($rawTitle, $contact, $lead);
        $message = $this->interpolateTokens($rawMessage, $contact, $lead);

        $recipients = collect();

        if (! empty($config['user_id'])) {
            $target = User::find($config['user_id']);
            if ($target) {
                $recipients->push($target);
            }
        }

        if (! empty($config['user_ids']) && is_array($config['user_ids'])) {
            $users = User::whereIn('id', $config['user_ids'])->get();
            $recipients = $recipients->merge($users);
        }

        if ($contact->assignedUser && empty($config['user_id']) && empty($config['user_ids'])) {
            $recipients->push($contact->assignedUser);
        }

        if ($recipients->isEmpty()) {
            $admin = User::where('role', UserRole::SuperAdmin->value)->first();
            if ($admin) {
                $recipients->push($admin);
            }
        }

        $recipients = $recipients->unique('id');

        foreach ($recipients as $recipient) {
            $recipient->notify(new AutomationNotification(
                title: $title,
                message: $message,
                data: [
                    'contact_id' => $contact->id,
                    'contact_name' => $contact->full_name,
                    'lead_id' => $lead?->id,
                    'type' => 'sequence_step',
                ]
            ));
        }

        return [
            'recipients_count' => $recipients->count(),
            'title' => $title,
        ];
    }

    /**
     * Interpolate template placeholders like {{contact.first_name}}, {{contact.name}}, {{lead.score}}, etc.
     */
    public function interpolateTokens(string $template, Contact $contact, ?Lead $lead = null): string
    {
        $tokens = [
            '{{contact.first_name}}' => $contact->first_name ?: 'Client',
            '{{contact.last_name}}' => $contact->last_name ?: '',
            '{{contact.name}}' => $contact->full_name ?: 'Client',
            '{{contact.phone}}' => $contact->phone ?: '',
            '{{contact.location}}' => $contact->location ?: 'Nigeria',
            '{{lead.score}}' => (string) ($lead?->score ?? '50'),
            '{{lead.temperature}}' => (string) ($lead?->temperature instanceof \BackedEnum ? $lead->temperature->value : ($lead?->temperature ?? 'warm')),
            '{{lead.title}}' => $lead?->title ?: '',
        ];

        return strtr($template, $tokens);
    }
}
