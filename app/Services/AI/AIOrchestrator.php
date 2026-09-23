<?php

namespace App\Services\AI;

use App\Enums\AIIntent;
use App\Models\Conversation;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\IntentResult;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Central AI Orchestrator for Bamcom AI CRM.
 *
 * Coordinates intent classification, context building, provider selection,
 * secure tool execution, and response synthesis.
 */
class AIOrchestrator
{
    public function __construct(
        protected AIProviderInterface $provider,
        protected ContextBuilder $contextBuilder,
        protected IntentClassifier $intentClassifier,
        protected KnowledgeService $knowledgeService,
        protected ToolRegistry $toolRegistry
    ) {}

    /**
     * Process an incoming customer message turn in a conversation.
     *
     * @param  array<string, mixed>  $options
     */
    public function processConversationTurn(
        Conversation $conversation,
        string $incomingMessage,
        array $options = []
    ): AIResponse {
        $startTime = microtime(true);

        // 1. Detect Customer Intent & Entities
        $intentResult = $this->intentClassifier->classify($incomingMessage);

        Log::info('AI Turn Ingested', [
            'conversation_id' => $conversation->id,
            'intent' => $intentResult->intent->value,
            'confidence' => $intentResult->confidence,
            'method' => $intentResult->method,
        ]);

        // 2. Handle Human Handover requests directly if flagged
        if ($intentResult->requiresHumanTakeover || $intentResult->intent === AIIntent::HumanHandover) {
            $this->toolRegistry->execute('escalate_to_human', [
                'reason' => 'Client requested human representative',
            ], ['conversation' => $conversation]);

            $escalationMessage = "I have notified our sales team! A human representative has been assigned to your conversation and will respond shortly.\n\n"
                .'In the meantime, feel free to leave any specific property questions or preferred inspection dates here.';

            return new AIResponse(
                content: $escalationMessage,
                toolCalls: [],
                usage: ['tokens' => 0],
                provider: $this->provider->getProviderName(),
                finishReason: 'escalated'
            );
        }

        // 3. Assemble Full Grounded Prompt Messages
        $messages = $this->contextBuilder->buildPromptMessages($conversation, $incomingMessage, $options);

        // 4. Retrieve Whitelisted Tool Schemas
        $toolSchemas = $this->toolRegistry->getToolSchemas();

        $callOptions = array_merge([
            'tools' => $toolSchemas,
            'temperature' => (float) config('ai.providers.'.$this->provider->getProviderName().'.temperature', 0.2),
        ], $options);

        // 5. Invoke Configured AI Provider
        $response = $this->provider->generateResponse($messages, $callOptions);

        // 6. Handle Tool Calling If Requested by Model
        if ($response->hasToolCalls()) {
            $toolResults = [];

            foreach ($response->toolCalls as $call) {
                $toolName = $call['name'] ?? '';
                $toolArgs = (array) ($call['arguments'] ?? []);

                try {
                    $result = $this->toolRegistry->execute($toolName, $toolArgs, [
                        'conversation' => $conversation,
                        'contact' => $conversation->contact,
                        'intent' => $intentResult,
                    ]);

                    $toolResults[$toolName] = $result;
                } catch (\Throwable $e) {
                    $toolResults[$toolName] = ['error' => $e->getMessage()];
                }
            }

            // Synthesize follow-up answer incorporating tool execution results
            $followupMessages = $messages;
            $followupMessages[] = [
                'role' => 'assistant',
                'content' => $response->content ?: 'Invoked tool: '.json_encode(array_keys($toolResults)),
            ];
            $followupMessages[] = [
                'role' => 'user',
                'content' => 'TOOL RESULTS: '.json_encode($toolResults)."\n\nPlease provide a clear, helpful, and concise response to the client based on these verified results.",
            ];

            // Second pass without tools to synthesize final WhatsApp text
            $finalResponse = $this->provider->generateResponse($followupMessages, ['tools' => []]);

            return new AIResponse(
                content: $finalResponse->content,
                toolCalls: $response->toolCalls,
                usage: [
                    'turn_1_tokens' => $response->usage['total_tokens'] ?? 0,
                    'turn_2_tokens' => $finalResponse->usage['total_tokens'] ?? 0,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ],
                provider: $this->provider->getProviderName(),
                finishReason: 'tool_execution_complete',
                rawResponse: [
                    'turn_1' => $response->rawResponse,
                    'turn_2' => $finalResponse->rawResponse,
                    'tool_results' => $toolResults,
                ]
            );
        }

        return $response;
    }

    /**
     * Direct intent classification utility.
     */
    public function classifyIntent(string $message): IntentResult
    {
        return $this->intentClassifier->classify($message);
    }

    public function getActiveProvider(): AIProviderInterface
    {
        return $this->provider;
    }

    public function getKnowledgeService(): KnowledgeService
    {
        return $this->knowledgeService;
    }

    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    public function getContextBuilder(): ContextBuilder
    {
        return $this->contextBuilder;
    }
}
