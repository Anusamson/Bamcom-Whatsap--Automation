<?php

namespace App\Services\AI;

use App\Enums\AIIntent;
use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\HandoverTrigger;
use App\Enums\LeadTemperature;
use App\Enums\UserStatus;
use App\Jobs\SendWhatsAppResponseJob;
use App\Models\Activity;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\HandoverRequiredNotification;
use App\Services\AI\DTOs\IntentResult;
use App\Services\Lead\LeadScoringService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Enterprise AI-to-Human Handover Management Service.
 *
 * Coordinates:
 * - Handover trigger detection across 7 distinct operational scenarios
 * - Sales representative assignment
 * - Immediate AI pause & status updates
 * - In-app representative notifications
 * - CRM follow-up task generation
 * - Detailed audit activity logging
 * - Authorized resumption back to AI or Hybrid mode
 */
class HandoverService
{
    /**
     * Regex patterns for customer requesting human assistance.
     */
    protected const PATTERN_CUSTOMER_REQUEST = '/\b(human|agent|representative|rep|real person|person|manager|call me|speak with someone|speak to someone|talk to someone|talk to a person|transfer me)\b/i';

    /**
     * Regex patterns for customer complaints, disputes, and accusations.
     */
    protected const PATTERN_COMPLAINT = '/\b(scam|fraud|fraudulent|police|lawyer|court|sue|illegal|cheat|dispute|bad service|terrible service|refund|stole|lying|complaint|file a complaint|unacceptable)\b/i';

    /**
     * Regex patterns for customer payment readiness and transaction inquiries.
     */
    protected const PATTERN_PAYMENT_READINESS = '/\b(ready to pay|want to pay|make payment|send account details|send bank details|send the account|account number to pay|where to pay|transfer the deposit|proof of payment|paid the deposit|payment receipt|transfer money now|ready to buy)\b/i';

    /**
     * Regex patterns for price bargaining, discounts, and negotiations.
     */
    protected const PATTERN_NEGOTIATION = '/\b(discount|negotiate|negotiable|last price|reduce the price|price cut|best offer|cheaper|reduction|best price|slash the price|promo discount|special price)\b/i';

    /**
     * Regex patterns for unsupported, complex legal, or non-catalog requests.
     */
    protected const PATTERN_UNSUPPORTED = '/\b(joint venture|jv proposal|mortgage partnership|power of attorney|diaspora proxy|sublease|litigation|tenancy dispute|commercial lease|court judgment|certificate of occupancy dispute)\b/i';

    /**
     * Detect whether an incoming message or conversation state triggers an AI-to-human handover.
     */
    public function detectTrigger(
        Conversation $conversation,
        string $message,
        ?IntentResult $intentResult = null
    ): ?HandoverTrigger {
        $trimmed = trim($message);

        // 1. Customer Requests Human
        if (
            $intentResult?->requiresHumanTakeover ||
            $intentResult?->intent === AIIntent::HumanHandover ||
            preg_match(self::PATTERN_CUSTOMER_REQUEST, $trimmed)
        ) {
            return HandoverTrigger::CustomerRequest;
        }

        // 2. Complaint or Dispute
        if (preg_match(self::PATTERN_COMPLAINT, $trimmed)) {
            return HandoverTrigger::Complaint;
        }

        // 3. Payment Readiness & Deposit Action
        if (preg_match(self::PATTERN_PAYMENT_READINESS, $trimmed)) {
            return HandoverTrigger::PaymentReadiness;
        }

        // 4. Negotiation & Discounts
        if (preg_match(self::PATTERN_NEGOTIATION, $trimmed)) {
            return HandoverTrigger::Negotiation;
        }

        // 5. Unsupported or Complex Legal Request
        if (preg_match(self::PATTERN_UNSUPPORTED, $trimmed)) {
            return HandoverTrigger::UnsupportedRequest;
        }

        // 6. High Lead Score (VIP/Hot Lead Requiring Human Closing)
        $lead = $conversation->contact?->leads()->latest()->first();
        if ($lead) {
            $isHotTemp = $lead->temperature instanceof LeadTemperature
                ? $lead->temperature === LeadTemperature::Hot
                : (is_string($lead->temperature) && strtolower($lead->temperature) === 'hot');

            if ($lead->score >= 70 || $isHotTemp || (bool) $lead->is_hot) {
                // If the lead has high intent and is actively asking about purchasing
                if (mb_strlen($trimmed) >= 3 && ! $conversation->isHuman()) {
                    return HandoverTrigger::HighLeadScore;
                }
            }
        }

        // 7. AI Uncertainty / Low Confidence
        if ($intentResult !== null && ($intentResult->confidence < 0.60 || $intentResult->intent === AIIntent::Unknown)) {
            return HandoverTrigger::AiUncertainty;
        }

        return null;
    }

    /**
     * Execute the complete handover pipeline:
     * 1. Agent assignment
     * 2. AI pause
     * 3. Representative notification
     * 4. Task creation
     * 5. Activity logging
     * 6. Customer WhatsApp acknowledgment
     *
     * @param  array<string, mixed>  $context
     * @return array{status: string, trigger: string, mode: string, assigned_user: ?string, task_id: int, message: string}
     */
    public function executeHandover(
        Conversation $conversation,
        HandoverTrigger $trigger,
        array $context = []
    ): array {
        $startTime = microtime(true);
        $contact = $conversation->contact;
        $reason = (string) ($context['reason'] ?? $trigger->label());
        $incomingMessageId = $context['incoming_message']?->id ?? $context['message_id'] ?? null;

        // 1. Agent Assignment: Resolve or allocate dedicated sales rep
        $assignedUser = $this->assignRepresentative($conversation);

        // 2. AI Pause: Update mode to Human, status to Open, record pause metadata
        $metadata = $conversation->metadata ?? [];
        $metadata['ai_paused'] = true;
        $metadata['ai_paused_at'] = now()->toIso8601String();
        $metadata['ai_processing'] = false;
        $metadata['handover'] = [
            'trigger' => $trigger->value,
            'trigger_label' => $trigger->label(),
            'priority' => $trigger->priority(),
            'reason' => $reason,
            'assigned_user_id' => $assignedUser?->id,
            'assigned_user_name' => $assignedUser?->name,
            'escalated_at' => now()->toIso8601String(),
        ];

        $conversation->update([
            'mode' => ConversationMode::Human,
            'status' => ConversationStatus::Open,
            'assigned_user_id' => $assignedUser?->id ?? $conversation->assigned_user_id,
            'metadata' => $metadata,
        ]);

        // 3. Representative Notification: Dispatch in-app notification to assigned rep
        if ($assignedUser) {
            $assignedUser->notify(new HandoverRequiredNotification($conversation, $trigger, $reason));
        }

        // 4. Task Creation: Generate CRM sales follow-up task
        $task = $this->createFollowUpTask($conversation, $trigger, $assignedUser, $reason);

        // 5. Activity Logging: Record comprehensive audit entry
        Activity::create([
            'lead_id' => $contact?->leads()->latest()->value('id'),
            'user_id' => $assignedUser?->id,
            'activity_type' => 'ai_handover_executed',
            'description' => "AI paused and conversation handed over to {$assignedUser?->name}: {$trigger->label()}",
            'properties' => [
                'conversation_id' => $conversation->id,
                'contact_id' => $contact?->id,
                'contact_name' => $contact?->full_name,
                'trigger' => $trigger->value,
                'trigger_label' => $trigger->label(),
                'priority' => $trigger->priority(),
                'reason' => $reason,
                'assigned_user_id' => $assignedUser?->id,
                'assigned_user_name' => $assignedUser?->name,
                'task_id' => $task->id,
                'message_id' => $incomingMessageId,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ],
        ]);

        if ($trigger === HandoverTrigger::CustomerRequest) {
            Activity::create([
                'lead_id' => $contact?->leads()->latest()->value('id'),
                'user_id' => $assignedUser?->id,
                'activity_type' => 'human_handover_requested',
                'description' => 'Customer requested a human sales representative.',
                'properties' => [
                    'conversation_id' => $conversation->id,
                    'contact_id' => $contact?->id,
                    'trigger' => $trigger->value,
                ],
            ]);
        }

        // Award lead scoring points for payment readiness / process request
        $lead = $contact?->leads()->latest()->first();
        if ($lead && $trigger === HandoverTrigger::PaymentReadiness) {
            app(LeadScoringService::class)->recordEvent(
                $lead,
                'payment_process_request',
                ['reason' => $reason],
                $assignedUser,
                source: 'ai_agent'
            );
        }

        Log::info("Handover executed for conversation #{$conversation->id}", [
            'trigger' => $trigger->value,
            'assigned_user_id' => $assignedUser?->id,
            'task_id' => $task->id,
        ]);

        // 6. Outbound WhatsApp Acknowledgment to Customer
        $customerAcknowledgment = (string) ($context['custom_reply'] ?? $trigger->customerAcknowledgment());
        SendWhatsAppResponseJob::dispatch($conversation, $customerAcknowledgment, $incomingMessageId);

        return [
            'status' => 'escalated',
            'trigger' => $trigger->value,
            'mode' => ConversationMode::Human->value,
            'action_taken' => 'human_handover_executed',
            'assigned_user' => $assignedUser?->name,
            'task_id' => $task->id,
            'message' => $customerAcknowledgment,
            'response' => $customerAcknowledgment,
        ];
    }

    /**
     * Resolve or assign the most appropriate sales representative for the conversation.
     */
    public function assignRepresentative(Conversation $conversation): ?User
    {
        // 1. Keep existing assigned representative if already present
        if ($conversation->assigned_user_id) {
            $existing = User::find($conversation->assigned_user_id);
            if ($existing && ($existing->status === UserStatus::Active || (string) ($existing->status->value ?? $existing->status) === 'active')) {
                return $existing;
            }
        }

        // 2. Use representative assigned to contact's active lead
        $lead = $conversation->contact?->leads()->latest()->first();
        if ($lead?->assigned_user_id) {
            $leadRep = User::find($lead->assigned_user_id);
            if ($leadRep && ($leadRep->status === UserStatus::Active || (string) ($leadRep->status->value ?? $leadRep->status) === 'active')) {
                return $leadRep;
            }
        }

        // 3. Fallback: Allocate available active sales representative / agent
        $candidate = User::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->where('role', 'agent')
                    ->orWhere('role', 'sales')
                    ->orWhere('role', 'admin');
            })
            ->oldest('id')
            ->first();

        return $candidate ?: User::first();
    }

    /**
     * Create an actionable CRM sales task for the assigned representative.
     */
    public function createFollowUpTask(
        Conversation $conversation,
        HandoverTrigger $trigger,
        ?User $assignedUser,
        string $reason
    ): Activity {
        $contact = $conversation->contact;
        $lead = $contact?->leads()->latest()->first();

        $priority = $trigger->priority();
        $dueHours = match ($priority) {
            'urgent' => 2,
            'high' => 4,
            default => 24,
        };

        $dueDate = now()->addHours($dueHours)->toDateTimeString();
        $title = "[Handover - {$trigger->label()}] Follow up with {$contact?->full_name}";

        return Activity::create([
            'lead_id' => $lead?->id,
            'user_id' => $assignedUser?->id,
            'activity_type' => 'task',
            'description' => "{$title}: {$reason}",
            'properties' => [
                'title' => $title,
                'task_description' => $reason,
                'trigger' => $trigger->value,
                'due_date' => $dueDate,
                'priority' => $priority,
                'assigned_user_id' => $assignedUser?->id,
                'conversation_id' => $conversation->id,
                'contact_id' => $contact?->id,
                'created_by_ai' => true,
                'status' => 'pending',
            ],
        ]);
    }

    /**
     * Return a conversation to AI or Hybrid mode by an authorized user.
     *
     * @throws InvalidArgumentException
     */
    public function resumeAi(
        Conversation $conversation,
        ConversationMode $targetMode,
        ?User $user = null
    ): Conversation {
        if (! in_array($targetMode, [ConversationMode::Ai, ConversationMode::Hybrid], true)) {
            throw new InvalidArgumentException("Cannot resume conversation to mode '{$targetMode->value}'. Target mode must be 'ai' or 'hybrid'.");
        }

        $metadata = $conversation->metadata ?? [];
        $metadata['ai_paused'] = false;
        $metadata['ai_resumed_at'] = now()->toIso8601String();
        $metadata['ai_resumed_by_user_id'] = $user?->id;
        $metadata['ai_resumed_by_user_name'] = $user?->name;

        $conversation->update([
            'mode' => $targetMode,
            'status' => ConversationStatus::Open,
            'metadata' => $metadata,
        ]);

        Activity::create([
            'lead_id' => $conversation->contact?->leads()->latest()->value('id'),
            'user_id' => $user?->id,
            'activity_type' => 'ai_resumed',
            'description' => "Conversation returned to {$targetMode->label()} by {$user?->name}",
            'properties' => [
                'conversation_id' => $conversation->id,
                'contact_id' => $conversation->contact_id,
                'resumed_mode' => $targetMode->value,
                'resumed_by_user_id' => $user?->id,
                'resumed_by_name' => $user?->name,
                'resumed_at' => now()->toIso8601String(),
            ],
        ]);

        Log::info("Conversation #{$conversation->id} resumed to {$targetMode->value} mode by user #{$user?->id}");

        return $conversation;
    }
}
