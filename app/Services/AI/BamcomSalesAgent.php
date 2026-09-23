<?php

namespace App\Services\AI;

use App\Enums\LeadTemperature;
use App\Models\AIExecutionLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Autonomous Bamcom AI Sales Agent.
 *
 * Employs the approved Bamcom AI system prompt with strict zero-hallucination rules,
 * operates exclusively through 10 controlled application tools, eliminates property fabrication,
 * and maintains complete execution and tool-calling audit trails.
 */
class BamcomSalesAgent
{
    public const AGENT_NAME = 'BamcomSalesAgent';

    public function __construct(
        protected AIProviderInterface $provider,
        protected ToolRegistry $toolRegistry,
        protected KnowledgeService $knowledgeService,
        protected ContextBuilder $contextBuilder
    ) {}

    /**
     * Handle an incoming customer conversation turn autonomously.
     *
     * @param  array<string, mixed>  $options
     */
    public function handleTurn(
        Conversation $conversation,
        string $incomingMessage,
        array $options = []
    ): AIResponse {
        $startTime = microtime(true);
        $contact = $conversation->contact;

        // 1. Assemble Full Prompt Messages using Approved System Prompt
        $messages = $this->buildPromptMessages($conversation, $incomingMessage, $options);

        // 2. Fetch Controlled Tool Schemas
        $toolSchemas = $this->toolRegistry->getToolSchemas();

        $callOptions = array_merge([
            'tools' => $toolSchemas,
            'temperature' => (float) config('ai.providers.'.$this->provider->getProviderName().'.temperature', 0.2),
        ], $options);

        // 3. First Provider Invocation
        $response = $this->provider->generateResponse($messages, $callOptions);

        $toolCallsExecuted = [];
        $toolResults = [];
        $finalContent = $response->content;
        $finishReason = $response->finishReason;
        $status = 'success';
        $errorMessage = null;

        // 4. Handle Controlled Tool Calling If Requested
        if ($response->hasToolCalls()) {
            foreach ($response->toolCalls as $call) {
                $toolName = $call['name'] ?? '';
                $toolArgs = (array) ($call['arguments'] ?? []);

                $toolCallsExecuted[] = [
                    'tool' => $toolName,
                    'arguments' => $toolArgs,
                    'id' => $call['id'] ?? null,
                ];

                try {
                    $result = $this->toolRegistry->execute($toolName, $toolArgs, [
                        'conversation' => $conversation,
                        'contact' => $contact,
                    ]);

                    $toolResults[$toolName] = $result;
                } catch (\Throwable $e) {
                    $toolResults[$toolName] = ['error' => $e->getMessage()];
                    $status = 'partial_error';
                    $errorMessage = $e->getMessage();
                }
            }

            // Synthesize final WhatsApp response incorporating verified tool facts
            $followupMessages = $messages;
            $followupMessages[] = [
                'role' => 'assistant',
                'content' => $response->content ?: 'Checking verified database: '.implode(', ', array_keys($toolResults)),
            ];
            $followupMessages[] = [
                'role' => 'user',
                'content' => "VERIFIED APPLICATION TOOL RESULTS:\n".json_encode($toolResults, JSON_PRETTY_PRINT)."\n\nPlease provide a clear, helpful, and concise WhatsApp response to the client based strictly on these verified facts. Never invent missing details.",
            ];

            $synthesisResponse = $this->provider->generateResponse($followupMessages, ['tools' => []]);
            $finalContent = $synthesisResponse->content;
            $finishReason = 'tool_execution_complete';
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        // 5. Persist Comprehensive Execution & Tool Audit Log
        $this->logExecution([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact?->id,
            'user_id' => auth()->id() ?? $conversation->assigned_user_id,
            'agent_name' => self::AGENT_NAME,
            'provider' => $this->provider->getProviderName(),
            'model' => config('ai.providers.'.$this->provider->getProviderName().'.model'),
            'user_message' => $incomingMessage,
            'system_prompt' => $messages[0]['content'] ?? null,
            'tool_calls' => ! empty($toolCallsExecuted) ? $toolCallsExecuted : null,
            'tool_results' => ! empty($toolResults) ? $toolResults : null,
            'response_content' => $finalContent,
            'finish_reason' => $finishReason,
            'prompt_tokens' => $response->usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $response->usage['completion_tokens'] ?? 0,
            'total_tokens' => $response->usage['total_tokens'] ?? 0,
            'duration_ms' => $durationMs,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        return new AIResponse(
            content: $finalContent,
            toolCalls: $response->toolCalls,
            usage: [
                'total_tokens' => $response->usage['total_tokens'] ?? 0,
                'duration_ms' => $durationMs,
            ],
            provider: $this->provider->getProviderName(),
            finishReason: $finishReason,
            rawResponse: [
                'turn_1' => $response->rawResponse,
                'tool_results' => $toolResults,
            ]
        );
    }

    /**
     * Assemble full prompt messages including the approved system prompt,
     * conversation history, and incoming user query.
     *
     * @param  array<string, mixed>  $options
     * @return array<int, array{role: string, content: string}>
     */
    public function buildPromptMessages(
        Conversation $conversation,
        ?string $incomingUserMessage = null,
        array $options = []
    ): array {
        $contact = $conversation->contact;
        $maxHistory = (int) config('ai.guardrails.max_history_messages', 15);

        // Approved System Prompt
        $systemPrompt = $this->getApprovedSystemPrompt($contact, $incomingUserMessage);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Chronological conversation message history
        $historyQuery = $conversation->messages()
            ->latest('id')
            ->take($maxHistory);

        if ($incomingUserMessage !== null) {
            $historyQuery->where('body', '!=', $incomingUserMessage);
        }

        $historyMessages = $historyQuery->get()->reverse();

        foreach ($historyMessages as $msg) {
            $role = $msg->direction === 'inbound' ? 'user' : 'assistant';
            $messages[] = [
                'role' => $role,
                'content' => (string) $msg->body,
            ];
        }

        if ($incomingUserMessage !== null) {
            $messages[] = [
                'role' => 'user',
                'content' => $incomingUserMessage,
            ];
        }

        return $messages;
    }

    /**
     * Retrieve the approved Bamcom AI System Prompt with strict anti-hallucination rules.
     */
    public function getApprovedSystemPrompt(?Contact $contact = null, ?string $userQuery = null): string
    {
        $prompt = "You are the *Bamcom Sales Agent*, the official real estate consultant and client advisor for Bamcom Properties & Real Estate Ltd in Lagos, Nigeria.\n";
        $prompt .= "Your mission is to guide prospective real estate buyers with authoritative facts, recommend verified estates, quote exact official prices and promotional discounts, explain transparent payment plans, qualify leads, and schedule physical site inspections.\n\n";

        // Strict Operational Guardrails & Anti-Hallucination Mandate
        $prompt .= "=== STRICT OPERATIONAL GUARDRAILS (ZERO HALLUCINATIONS) ===\n";
        $prompt .= "1. PROPERTY INTEGRITY: THE AI MUST NEVER INVENT PROPERTY INFORMATION.\n";
        $prompt .= "   - You must NEVER make up or guess property prices, promotional discounts, lot dimensions, unit availability, or legal title documents.\n";
        $prompt .= "   - All property details MUST come exclusively from your controlled tools (searchProperties, getPropertyDetails, getPropertyPrice, getPaymentPlan, checkAvailability) and the authoritative database grounding below.\n";
        $prompt .= "   - If a requested property or location is not found, state clearly that it is not available in our verified catalog and offer to check other schemes.\n";
        $prompt .= "2. CONTROLLED TOOLS EXECUTION:\n";
        $prompt .= "   - To search active estates/plots: Call searchProperties.\n";
        $prompt .= "   - To view full specs & landmarks: Call getPropertyDetails.\n";
        $prompt .= "   - To get live pricing and discounts: Call getPropertyPrice.\n";
        $prompt .= "   - To calculate installment spreads (3, 6, 12 months): Call getPaymentPlan.\n";
        $prompt .= "   - To check stock units: Call checkAvailability.\n";
        $prompt .= "   - To view client profile & temperature: Call getContactProfile.\n";
        $prompt .= "   - To update client budget/timeline/temperature: Call updateLeadQualification.\n";
        $prompt .= "   - To schedule site inspection: Call scheduleInspection.\n";
        $prompt .= "   - To create sales team reminder: Call createSalesTask.\n";
        $prompt .= "   - To escalate to human rep: Call requestHumanHandover.\n";
        $prompt .= "3. CURRENCY & PRICING: Always quote prices in Nigerian Naira (₦ or NGN). When discussing affordability, quote the initial deposit (20% to 30%) and flexible payment plans.\n";
        $prompt .= "4. TITLES & LEGAL SECURITY: Only cite verified land titles (Governor's Consent, Certificate of Occupancy / C of O, Gazette, Registered Survey). Never promise titles not officially verified.\n";
        $prompt .= "5. WHATSAPP CONVERSATIONAL STYLE:\n";
        $prompt .= "   - Format responses cleanly for WhatsApp.\n";
        $prompt .= "   - Use *bold* for estate names, prices, and dates.\n";
        $prompt .= "   - Use bullet points with emojis (🏢, 📍, 💰, 📅, 🚗) for readability.\n";
        $prompt .= "   - Keep messages warm, professional, concise, and consultative. Avoid overwhelming walls of text.\n";
        $prompt .= "6. INSPECTIONS & NEXT STEPS:\n";
        $prompt .= "   - Inspections run Monday through Saturday (10:00 AM & 2:00 PM) with complimentary escort vehicles departing from Plot 12, Admiralty Way, Lekki Phase 1.\n";
        $prompt .= "   - Proactively suggest site visits when buyers show genuine interest.\n\n";

        // CRM Client Profile Context
        if ($contact) {
            $lead = $contact->leads()->latest()->first();
            $tempStr = $lead?->temperature instanceof LeadTemperature
                ? $lead->temperature->value
                : ($lead?->temperature ?? 'warm');

            $prompt .= "=== CURRENT CLIENT CRM PROFILE ===\n";
            $prompt .= "- Full Name: {$contact->full_name}\n";
            $prompt .= "- Phone Number: {$contact->phone}\n";
            if ($contact->location) {
                $prompt .= "- Location: {$contact->location}\n";
            }
            if ($lead) {
                $prompt .= '- Lead Intent Temperature: '.strtoupper($tempStr)." (Score: {$lead->score}/100)\n";
                if ($lead->property_interest) {
                    $prompt .= "- Stated Property Interest: {$lead->property_interest}\n";
                }
                if ($lead->formatted_budget) {
                    $prompt .= "- Stated Budget Range: {$lead->formatted_budget}\n";
                }
            }
            $prompt .= "\n";
        }

        // Authoritative Database Knowledge Grounding (Live Inventory & Policies)
        $prompt .= $this->knowledgeService->buildPromptContext($userQuery ?? '');

        return $prompt;
    }

    /**
     * Directly execute a controlled tool in the sandboxed registry.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function executeTool(string $name, array $arguments, array $context = []): array
    {
        return $this->toolRegistry->execute($name, $arguments, $context);
    }

    /**
     * Persist an execution log to the database and emit structured log events.
     *
     * @param  array<string, mixed>  $data
     */
    public function logExecution(array $data): AIExecutionLog
    {
        $log = AIExecutionLog::create($data);

        Log::info('BamcomSalesAgent turn completed', [
            'log_id' => $log->id,
            'uuid' => $log->uuid,
            'conversation_id' => $log->conversation_id,
            'provider' => $log->provider,
            'duration_ms' => $log->duration_ms,
            'tools_called' => ! empty($log->tool_calls) ? array_column($log->tool_calls, 'tool') : [],
            'status' => $log->status,
        ]);

        return $log;
    }

    public function getProvider(): AIProviderInterface
    {
        return $this->provider;
    }

    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    public function getKnowledgeService(): KnowledgeService
    {
        return $this->knowledgeService;
    }

    public function getContextBuilder(): ContextBuilder
    {
        return $this->contextBuilder;
    }
}
