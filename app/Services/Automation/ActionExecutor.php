<?php

namespace App\Services\Automation;

use App\Enums\AutomationActionType;
use App\Enums\HandoverTrigger;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Enums\UserRole;
use App\Models\AutomationAction;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\User;
use App\Notifications\AutomationNotification;
use App\Services\AI\HandoverService;
use App\Services\Contact\ContactService;
use App\Services\Conversation\ConversationService;
use App\Services\Lead\LeadScoringService;
use App\Services\Lead\PipelineService;
use App\Services\Task\TaskService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Execution engine for all 9 automated CRM actions.
 */
class ActionExecutor
{
    public function __construct(
        protected WhatsAppMessageService $whatsAppService,
        protected TaskService $taskService,
        protected ContactService $contactService,
        protected PipelineService $pipelineService,
        protected LeadScoringService $leadScoringService,
        protected HandoverService $handoverService,
        protected ConversationService $conversationService
    ) {}

    /**
     * Execute an automation action on a given run.
     *
     * @param  array<string, mixed>  $context
     * @return array{success: bool, action_type: string, output?: array<string, mixed>, error?: string}
     */
    public function execute(AutomationAction $action, AutomationRun $run, array $context = []): array
    {
        $actionType = $action->action_type instanceof AutomationActionType
            ? $action->action_type
            : AutomationActionType::from((string) $action->action_type);

        $config = (array) $action->action_config;
        $subject = $run->subject;

        try {
            $output = match ($actionType) {
                AutomationActionType::SendWhatsApp => $this->executeSendWhatsApp($config, $subject, $context),
                AutomationActionType::CreateTask => $this->executeCreateTask($config, $subject, $context),
                AutomationActionType::AssignAgent => $this->executeAssignAgent($config, $subject, $context),
                AutomationActionType::ChangePipelineStage => $this->executeChangePipelineStage($config, $subject, $context),
                AutomationActionType::AddTag => $this->executeAddTag($config, $subject),
                AutomationActionType::RemoveTag => $this->executeRemoveTag($config, $subject),
                AutomationActionType::UpdateLeadScore => $this->executeUpdateLeadScore($config, $subject, $context),
                AutomationActionType::SendNotification => $this->executeSendNotification($config, $subject, $run),
                AutomationActionType::RequestHuman => $this->executeRequestHuman($config, $subject, $context),
            };

            return [
                'success' => true,
                'action_type' => $actionType->value,
                'output' => $output,
            ];
        } catch (Exception $e) {
            Log::error("Failed executing automation action [{$actionType->value}] for run #{$run->id}: {$e->getMessage()}", [
                'action_id' => $action->id,
                'run_id' => $run->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'action_type' => $actionType->value,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 1. SEND_WHATSAPP
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeSendWhatsApp(array $config, ?Model $subject, array $context): array
    {
        $contact = $this->resolveContact($subject);
        $phone = $config['phone'] ?? $contact?->phone;

        if (empty($phone)) {
            throw new Exception('No phone number available for WhatsApp outbound message.');
        }

        $rawBody = (string) ($config['message'] ?? $config['body'] ?? 'Hello from Bamcom CRM');
        $interpolatedBody = $this->interpolateTokens($rawBody, $subject, $context);

        $result = $this->whatsAppService->sendTextMessage(
            to: $phone,
            body: $interpolatedBody,
            contact: $contact
        );

        return [
            'phone' => $phone,
            'message' => $interpolatedBody,
            'whatsapp_result' => $result,
        ];
    }

    /**
     * 2. CREATE_TASK
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeCreateTask(array $config, ?Model $subject, array $context): array
    {
        $contact = $this->resolveContact($subject);
        $lead = $this->resolveLead($subject);
        $deal = $subject instanceof Deal ? $subject : null;

        $rawTitle = (string) ($config['title'] ?? 'Automated Follow-up Task');
        $interpolatedTitle = $this->interpolateTokens($rawTitle, $subject, $context);

        $rawDescription = isset($config['description'])
            ? $this->interpolateTokens((string) $config['description'], $subject, $context)
            : null;

        // Due date calculation
        $dueAt = now()->addDay();
        if (isset($config['due_in_hours'])) {
            $dueAt = now()->addHours((int) $config['due_in_hours']);
        } elseif (isset($config['due_in_days'])) {
            $dueAt = now()->addDays((int) $config['due_in_days']);
        } elseif (! empty($config['due_at'])) {
            $dueAt = Carbon::parse($config['due_at']);
        }

        $assignedUserId = $config['assigned_user_id'] ?? null;
        if (empty($assignedUserId) && $contact?->assigned_user_id) {
            $assignedUserId = $contact->assigned_user_id;
        }

        $priority = $config['priority'] ?? TaskPriority::Medium->value;
        $type = $config['type'] ?? TaskType::FollowUp->value;

        $task = $this->taskService->createTask([
            'title' => $interpolatedTitle,
            'description' => $rawDescription,
            'contact_id' => $contact?->id,
            'lead_id' => $lead?->id,
            'deal_id' => $deal?->id,
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
     * 3. ASSIGN_AGENT
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeAssignAgent(array $config, ?Model $subject, array $context): array
    {
        $contact = $this->resolveContact($subject);
        $lead = $this->resolveLead($subject);

        // Resolve target user: explicit user_id, or round-robin selection
        $targetUser = null;
        if (! empty($config['agent_id']) || ! empty($config['user_id'])) {
            $userId = (int) ($config['agent_id'] ?? $config['user_id']);
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
            throw new Exception('No active sales representative available for assignment.');
        }

        if ($contact) {
            $contact->update(['assigned_user_id' => $targetUser->id]);
        }

        if ($lead) {
            $lead->update(['assigned_user_id' => $targetUser->id]);
        }

        // Also assign active conversation if one exists
        if ($contact) {
            $activeConv = Conversation::where('contact_id', $contact->id)->latest()->first();
            if ($activeConv) {
                $this->conversationService->assignUser($activeConv, $targetUser);
            }
        }

        return [
            'assigned_user_id' => $targetUser->id,
            'assigned_user_name' => $targetUser->name,
            'contact_id' => $contact?->id,
            'lead_id' => $lead?->id,
        ];
    }

    /**
     * 4. CHANGE_PIPELINE_STAGE
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeChangePipelineStage(array $config, ?Model $subject, array $context): array
    {
        $lead = $this->resolveLead($subject);
        if (! $lead) {
            throw new Exception('Cannot change pipeline stage: subject is not a lead or has no associated lead.');
        }

        $targetStage = null;
        if (! empty($config['stage_id'])) {
            $targetStage = PipelineStage::find($config['stage_id']);
        } elseif (! empty($config['stage_name'])) {
            $targetStage = PipelineStage::where('name', $config['stage_name'])->first();
        }

        if (! $targetStage) {
            throw new Exception('Target pipeline stage not found for automation action.');
        }

        $updatedLead = $this->pipelineService->moveLeadStage($lead, $targetStage);

        return [
            'lead_id' => $updatedLead->id,
            'new_stage_id' => $targetStage->id,
            'new_stage_name' => $targetStage->name,
        ];
    }

    /**
     * 5. ADD_TAG
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeAddTag(array $config, ?Model $subject): array
    {
        if (! $subject) {
            throw new Exception('Subject not found for tag addition.');
        }

        $tagNames = (array) ($config['tag'] ?? $config['tags'] ?? $config['name'] ?? []);
        if (is_string($tagNames)) {
            $tagNames = [$tagNames];
        }

        $attached = [];
        foreach ($tagNames as $name) {
            if (empty(trim($name))) {
                continue;
            }

            if (method_exists($subject, 'attachTag')) {
                $subject->attachTag(trim($name));
                $attached[] = trim($name);
            }
        }

        return ['attached_tags' => $attached];
    }

    /**
     * 6. REMOVE_TAG
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeRemoveTag(array $config, ?Model $subject): array
    {
        if (! $subject) {
            throw new Exception('Subject not found for tag removal.');
        }

        $tagNames = (array) ($config['tag'] ?? $config['tags'] ?? $config['name'] ?? []);
        if (is_string($tagNames)) {
            $tagNames = [$tagNames];
        }

        $detached = [];
        foreach ($tagNames as $name) {
            if (empty(trim($name))) {
                continue;
            }

            if (method_exists($subject, 'detachTag')) {
                $subject->detachTag(trim($name));
                $detached[] = trim($name);
            }
        }

        return ['detached_tags' => $detached];
    }

    /**
     * 7. UPDATE_LEAD_SCORE
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeUpdateLeadScore(array $config, ?Model $subject, array $context): array
    {
        $lead = $this->resolveLead($subject);
        if (! $lead) {
            throw new Exception('Subject is not a lead and has no associated lead to adjust score.');
        }

        $points = (int) ($config['points'] ?? $config['delta'] ?? 10);
        $reason = (string) ($config['reason'] ?? 'Automated workflow score adjustment');

        $log = $this->leadScoringService->adjustScore(
            lead: $lead,
            pointsDelta: $points,
            reason: $reason,
            actor: null,
            context: $context
        );

        return [
            'lead_id' => $lead->id,
            'score_before' => $log->score_before,
            'score_after' => $log->score_after,
            'points_awarded' => $log->points_awarded,
        ];
    }

    /**
     * 8. SEND_NOTIFICATION
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function executeSendNotification(array $config, ?Model $subject, AutomationRun $run): array
    {
        $contact = $this->resolveContact($subject);
        $title = (string) ($config['title'] ?? 'Automation Notification');
        $message = (string) ($config['message'] ?? 'An automated workflow event occurred.');

        $title = $this->interpolateTokens($title, $subject, []);
        $message = $this->interpolateTokens($message, $subject, []);

        $recipients = collect();

        // 1. Specific user target
        if (! empty($config['user_id'])) {
            $targetUser = User::find($config['user_id']);
            if ($targetUser) {
                $recipients->push($targetUser);
            }
        }

        // 2. Assigned representative
        if (($config['target'] ?? '') === 'assigned_agent' || empty($config['user_id'])) {
            if ($contact?->assignedUser) {
                $recipients->push($contact->assignedUser);
            }
        }

        // 3. Super admins fallback if empty
        if ($recipients->isEmpty()) {
            $admins = User::where('role', UserRole::SuperAdmin->value)->get();
            $recipients = $admins;
        }

        $notification = new AutomationNotification(
            title: $title,
            message: $message,
            data: [
                'run_id' => $run->id,
                'workflow_id' => $run->workflow_id,
                'subject_type' => $run->subject_type,
                'subject_id' => $run->subject_id,
            ]
        );

        foreach ($recipients->unique('id') as $user) {
            $user->notify($notification);
        }

        return [
            'recipients_count' => $recipients->count(),
            'title' => $title,
        ];
    }

    /**
     * 9. REQUEST_HUMAN
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function executeRequestHuman(array $config, ?Model $subject, array $context): array
    {
        $contact = $this->resolveContact($subject);
        if (! $contact) {
            throw new Exception('No contact associated with subject to execute AI-to-human handover.');
        }

        $conversation = Conversation::where('contact_id', $contact->id)->latest()->first();
        if (! $conversation) {
            $conversation = $this->conversationService->findOrCreateActiveConversation($contact);
        }

        $triggerEnum = isset($config['trigger'])
            ? (HandoverTrigger::tryFrom((string) $config['trigger']) ?? HandoverTrigger::CustomerRequest)
            : HandoverTrigger::CustomerRequest;

        $reason = (string) ($config['reason'] ?? 'Automated workflow requested human representative handover');

        $result = $this->handoverService->executeHandover(
            conversation: $conversation,
            trigger: $triggerEnum,
            context: array_merge($context, ['reason' => $reason])
        );

        return [
            'conversation_id' => $conversation->id,
            'handover_result' => $result,
        ];
    }

    /**
     * Resolve Contact model from polymorphic subject.
     */
    protected function resolveContact(?Model $subject): ?Contact
    {
        if ($subject instanceof Contact) {
            return $subject;
        }

        if ($subject instanceof Lead) {
            return $subject->contact;
        }

        if ($subject instanceof Deal) {
            return $subject->contact;
        }

        if ($subject instanceof Conversation) {
            return $subject->contact;
        }

        return null;
    }

    /**
     * Resolve Lead model from polymorphic subject.
     */
    protected function resolveLead(?Model $subject): ?Lead
    {
        if ($subject instanceof Lead) {
            return $subject;
        }

        if ($subject instanceof Contact) {
            return $subject->leads()->latest()->first();
        }

        if ($subject instanceof Deal) {
            return $subject->lead;
        }

        return null;
    }

    /**
     * Interpolate template placeholders like {{contact.first_name}}, {{lead.score}}, etc.
     *
     * @param  array<string, mixed>  $context
     */
    protected function interpolateTokens(string $template, ?Model $subject, array $context = []): string
    {
        $contact = $this->resolveContact($subject);
        $lead = $this->resolveLead($subject);

        $tokens = [
            '{{contact.first_name}}' => $contact?->first_name ?? 'Client',
            '{{contact.last_name}}' => $contact?->last_name ?? '',
            '{{contact.name}}' => $contact?->full_name ?? 'Client',
            '{{contact.phone}}' => $contact?->phone ?? '',
            '{{contact.location}}' => $contact?->location ?? 'Nigeria',
            '{{lead.score}}' => (string) ($lead?->score ?? '50'),
            '{{lead.temperature}}' => (string) ($lead?->temperature instanceof \BackedEnum ? $lead->temperature->value : ($lead->temperature ?? 'warm')),
            '{{lead.title}}' => $lead?->title ?? '',
        ];

        // Also add any context values
        foreach ($context as $key => $val) {
            if (is_scalar($val)) {
                $tokens["{{event.{$key}}}"] = (string) $val;
                $tokens["{{context.{$key}}}"] = (string) $val;
            }
        }

        return strtr($template, $tokens);
    }
}
