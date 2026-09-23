<?php

namespace App\Services\AI;

use App\Enums\AIIntent;
use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Jobs\SendWhatsAppResponseJob;
use App\Models\Activity;
use App\Models\AIExecutionLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\Safety\AISafetyValidator;
use App\Services\Conversation\ConversationService;
use App\Services\Lead\LeadScoringService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * End-to-end conversation pipeline connecting incoming WhatsApp messages to BamcomSalesAgent.
 *
 * Workflow:
 * incoming message → contact → conversation → CRM context → intent detection
 * → AI generation → safety/business-rule validation → queued WhatsApp response
 *
 * Respects conversation modes:
 * - AI: Automatic queued outbound response
 * - Human: No AI outbound response
 * - Hybrid: AI suggestion only (stored in conversation metadata)
 *
 * Guaranteed Failure Handling: Never leaves conversation in an endless processing state.
 */
class ConversationAiPipeline
{
    public function __construct(
        protected BamcomSalesAgent $agent,
        protected IntentClassifier $intentClassifier,
        protected AISafetyValidator $safetyValidator,
        protected ConversationService $conversationService,
        protected HandoverService $handoverService
    ) {}

    /**
     * Process an incoming customer message turn through the AI pipeline.
     *
     * @param  array<string, mixed>  $options
     * @return array{status: string, mode: string, action_taken: string, response?: ?string, error?: ?string}
     */
    public function processTurn(
        Conversation $conversation,
        Message $incomingMessage,
        array $options = []
    ): array {
        $startTime = microtime(true);
        $messageText = trim((string) $incomingMessage->body);

        // 1. Respect conversation mode: Human mode suppresses all AI outbound responses
        if ($conversation->isHuman()) {
            Log::info("Conversation #{$conversation->id} is in Human mode. Skipping AI processing.");

            return [
                'status' => 'skipped',
                'mode' => ConversationMode::Human->value,
                'action_taken' => 'human_mode_suppressed',
            ];
        }

        // If no textual message content exists (e.g. bare media without caption), skip AI generation
        if ($messageText === '') {
            Log::info("Conversation #{$conversation->id} message has no text. Skipping AI generation.");

            return [
                'status' => 'skipped',
                'mode' => $conversation->mode->value,
                'action_taken' => 'empty_message_skipped',
            ];
        }

        // 2. Concurrency Lock & State Guard to prevent duplicate concurrent turns
        $lockKey = "ai:conversation:turn:{$conversation->id}";
        $lock = Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            Log::info("AI turn already processing for conversation #{$conversation->id}, skipping concurrent execution.");

            return [
                'status' => 'locked',
                'mode' => $conversation->mode->value,
                'action_taken' => 'concurrent_lock_active',
            ];
        }

        // Mark conversation as processing with timestamp in metadata
        $this->markProcessingState($conversation, true);

        try {
            // Refresh conversation and eager load CRM context
            $conversation->loadMissing([
                'contact.leads.property.estate',
                'contact.deals',
            ]);

            // 3. Customer Intent Detection
            $intentResult = $this->intentClassifier->classify($messageText);

            Log::info("Intent detected for conversation #{$conversation->id}", [
                'intent' => $intentResult->intent->value,
                'confidence' => $intentResult->confidence,
                'mode' => $conversation->mode->value,
            ]);

            // Record intent-based lead scoring milestones
            $activeLead = $conversation->contact?->leads()->latest()->first();
            if ($activeLead) {
                if ($intentResult->intent === AIIntent::PricingInquiry) {
                    app(LeadScoringService::class)->recordEvent(
                        $activeLead,
                        'price_enquiry',
                        ['message' => $messageText],
                        source: 'ai_agent'
                    );
                } elseif ($intentResult->intent === AIIntent::PaymentPlan) {
                    app(LeadScoringService::class)->recordEvent(
                        $activeLead,
                        'payment_plan_enquiry',
                        ['message' => $messageText],
                        source: 'ai_agent'
                    );
                }
            }

            // 4. Check for AI-to-Human Handover Triggers (7 Triggers via HandoverService)
            $handoverTrigger = $this->handoverService->detectTrigger($conversation, $messageText, $intentResult);

            if ($handoverTrigger !== null) {
                return $this->handoverService->executeHandover($conversation, $handoverTrigger, [
                    'incoming_message' => $incomingMessage,
                    'intent' => $intentResult->intent->value,
                ]);
            }

            // 5. AI Generation via BamcomSalesAgent (Zero-hallucination + 10 controlled tools)
            $aiResponse = $this->agent->handleTurn($conversation, $messageText, array_merge([
                'intent' => $intentResult,
            ], $options));

            $rawContent = $aiResponse->content ?? '';

            // 6. Safety and Business-Rule Validation
            $validation = $this->safetyValidator->validate($rawContent, [
                'conversation_id' => $conversation->id,
                'contact_id' => $conversation->contact_id,
                'intent' => $intentResult->intent->value,
            ]);

            if (! $validation['is_valid']) {
                return $this->handleSafetyViolation($conversation, $incomingMessage, $validation['violations'], $rawContent);
            }

            $validatedContent = $validation['sanitized_content'];

            // 7. Route based on Conversation Mode: AI vs Hybrid
            if ($conversation->isAi()) {
                // AI Mode = Automatic queued outbound response
                SendWhatsAppResponseJob::dispatch($conversation, $validatedContent, $incomingMessage->id);

                Activity::create([
                    'activity_type' => 'ai_response_dispatched',
                    'description' => "AI Sales Agent queued response to {$conversation->contact?->full_name}",
                    'properties' => [
                        'contact_id' => $conversation->contact_id,
                        'conversation_id' => $conversation->id,
                        'message_id' => $incomingMessage->id,
                        'intent' => $intentResult->intent->value,
                        'confidence' => $intentResult->confidence,
                        'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    ],
                ]);

                return [
                    'status' => 'success',
                    'mode' => ConversationMode::Ai->value,
                    'action_taken' => 'queued_outbound_response',
                    'response' => $validatedContent,
                ];
            }

            if ($conversation->isHybrid()) {
                // Hybrid Mode = AI suggestion only (stored in conversation metadata for human rep)
                $metadata = $conversation->metadata ?? [];
                $metadata['ai_suggestion'] = [
                    'content' => $validatedContent,
                    'intent' => $intentResult->intent->value,
                    'confidence' => $intentResult->confidence,
                    'created_at' => now()->toIso8601String(),
                ];

                $conversation->update([
                    'metadata' => $metadata,
                ]);

                Activity::create([
                    'activity_type' => 'ai_suggestion_created',
                    'description' => "AI suggestion generated for rep review ({$intentResult->intent->value})",
                    'properties' => [
                        'contact_id' => $conversation->contact_id,
                        'conversation_id' => $conversation->id,
                        'intent' => $intentResult->intent->value,
                        'suggestion' => mb_substr($validatedContent, 0, 150),
                    ],
                ]);

                return [
                    'status' => 'success',
                    'mode' => ConversationMode::Hybrid->value,
                    'action_taken' => 'saved_suggestion',
                    'response' => $validatedContent,
                ];
            }

            return [
                'status' => 'success',
                'mode' => $conversation->mode->value,
                'action_taken' => 'completed',
            ];
        } catch (Throwable $e) {
            // 8. Robust Failure Handling: Never leave the conversation stuck in endless processing
            Log::error("AI Pipeline failed for conversation #{$conversation->id}: {$e->getMessage()}", [
                'conversation_id' => $conversation->id,
                'exception' => $e,
            ]);

            return $this->handleTurnFailure($conversation, $incomingMessage, $e, $startTime);
        } finally {
            // ALWAYS clear processing flag and ensure conversation status is active/open
            $this->markProcessingState($conversation, false);
            $lock->release();
        }
    }

    /**
     * Handle immediate escalation to human agent.
     *
     * @return array{status: string, mode: string, action_taken: string, response: string}
     */
    protected function handleHumanHandover(
        Conversation $conversation,
        Message $incomingMessage,
        string $intentName
    ): array {
        $conversation->update([
            'mode' => ConversationMode::Human,
            'status' => ConversationStatus::Open,
        ]);

        Activity::create([
            'activity_type' => 'human_handover_requested',
            'description' => 'Customer requested a human sales representative.',
            'properties' => [
                'contact_id' => $conversation->contact_id,
                'conversation_id' => $conversation->id,
                'message_id' => $incomingMessage->id,
                'intent' => $intentName,
            ],
        ]);

        $handoverReply = "I have notified our sales team! A human representative has been assigned to your conversation and will respond shortly.\n\n"
            .'In the meantime, feel free to share any specific property preferences or inspection times here.';

        // In AI mode, inform the customer that a human has taken over
        SendWhatsAppResponseJob::dispatch($conversation, $handoverReply, $incomingMessage->id);

        return [
            'status' => 'escalated',
            'mode' => ConversationMode::Human->value,
            'action_taken' => 'human_handover_executed',
            'response' => $handoverReply,
        ];
    }

    /**
     * Handle response failing safety guardrails: block dispatch and escalate.
     *
     * @param  array<int, string>  $violations
     * @return array{status: string, mode: string, action_taken: string, error: string}
     */
    protected function handleSafetyViolation(
        Conversation $conversation,
        Message $incomingMessage,
        array $violations,
        string $rawContent
    ): array {
        $violationReason = implode('; ', $violations);

        // Escalate conversation to human representative
        $conversation->update([
            'mode' => ConversationMode::Human,
            'status' => ConversationStatus::Open,
        ]);

        AIExecutionLog::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'agent_name' => BamcomSalesAgent::AGENT_NAME,
            'provider' => config('ai.default_provider', 'openai'),
            'user_message' => (string) $incomingMessage->body,
            'response_content' => $rawContent,
            'finish_reason' => 'safety_rejected',
            'status' => 'safety_rejected',
            'error_message' => $violationReason,
        ]);

        Activity::create([
            'activity_type' => 'ai_safety_violation',
            'description' => 'AI output blocked by safety guardrails and escalated to human agent.',
            'properties' => [
                'contact_id' => $conversation->contact_id,
                'conversation_id' => $conversation->id,
                'violations' => $violations,
            ],
        ]);

        // Send safe fallback response if in AI mode
        $safeFallback = 'Thank you for contacting Bamcom Properties! A senior property consultant has been assigned to your message and will respond shortly.';
        SendWhatsAppResponseJob::dispatch($conversation, $safeFallback, $incomingMessage->id);

        return [
            'status' => 'rejected',
            'mode' => ConversationMode::Human->value,
            'action_taken' => 'safety_violation_escalated',
            'error' => $violationReason,
        ];
    }

    /**
     * Bulletproof failure handler: Logs errors, clears processing locks,
     * keeps conversation Open, and provides graceful customer fallback.
     *
     * @return array{status: string, mode: string, action_taken: string, error: string}
     */
    protected function handleTurnFailure(
        Conversation $conversation,
        Message $incomingMessage,
        Throwable $exception,
        float $startTime
    ): array {
        $metadata = $conversation->metadata ?? [];
        $metadata['ai_last_error'] = [
            'message' => $exception->getMessage(),
            'occurred_at' => now()->toIso8601String(),
        ];

        // Ensure status is never stuck in Pending; escalate to Human so the rep can handle it
        $conversation->update([
            'mode' => ConversationMode::Human,
            'status' => ConversationStatus::Open,
            'metadata' => $metadata,
        ]);

        // Audit failure in execution log
        AIExecutionLog::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'agent_name' => BamcomSalesAgent::AGENT_NAME,
            'provider' => config('ai.default_provider', 'openai'),
            'user_message' => (string) $incomingMessage->body,
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ]);

        // Log activity on CRM contact
        Activity::create([
            'activity_type' => 'ai_turn_failed',
            'description' => "AI processing failed, conversation routed to human: {$exception->getMessage()}",
            'properties' => [
                'contact_id' => $conversation->contact_id,
                'conversation_id' => $conversation->id,
                'message_id' => $incomingMessage->id,
            ],
        ]);

        // Send graceful fallback message to customer so they are not left hanging
        $fallbackMessage = 'Thank you for reaching out to Bamcom Properties! Our team has received your message and a sales advisor will reply shortly.';
        SendWhatsAppResponseJob::dispatch($conversation, $fallbackMessage, $incomingMessage->id);

        return [
            'status' => 'failed',
            'mode' => ConversationMode::Human->value,
            'action_taken' => 'failure_handled_and_escalated',
            'error' => $exception->getMessage(),
        ];
    }

    /**
     * Safely update the ai_processing flag in metadata and maintain active status.
     */
    protected function markProcessingState(Conversation $conversation, bool $isProcessing): void
    {
        $conversation->refresh();
        $metadata = $conversation->metadata ?? [];

        $metadata['ai_processing'] = $isProcessing;
        if ($isProcessing) {
            $metadata['ai_processing_started_at'] = now()->toIso8601String();
        } else {
            unset($metadata['ai_processing_started_at']);
        }

        $updateData = [
            'metadata' => $metadata,
        ];

        // When processing finishes, guarantee the conversation is not left in pending
        if (! $isProcessing && $conversation->status === ConversationStatus::Pending) {
            $updateData['status'] = ConversationStatus::Open;
        }

        $conversation->update($updateData);
    }
}
