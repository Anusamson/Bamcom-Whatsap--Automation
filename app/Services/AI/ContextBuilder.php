<?php

namespace App\Services\AI;

use App\Enums\LeadTemperature;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;

/**
 * Enterprise Prompt & Context Assembler for Bamcom AI Assistant.
 *
 * Integrates system personas, CRM contact and lead context, conversation thread history,
 * and authoritative database knowledge into structured message payloads.
 */
class ContextBuilder
{
    public function __construct(
        protected KnowledgeService $knowledgeService
    ) {}

    /**
     * Assemble full prompt message payload for LLM inference.
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

        // 1. Build authoritative System Instructions
        $systemInstructions = $this->buildSystemInstructions($contact, $incomingUserMessage);

        $messages = [
            ['role' => 'system', 'content' => $systemInstructions],
        ];

        // 2. Fetch past conversation message history in chronological order
        $historyQuery = $conversation->messages()
            ->latest('id')
            ->take($maxHistory);

        // If incomingUserMessage is provided, don't duplicate it from DB if it was already saved
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

        // 3. Append current incoming user query if provided
        if ($incomingUserMessage !== null) {
            $messages[] = [
                'role' => 'user',
                'content' => $incomingUserMessage,
            ];
        }

        return $messages;
    }

    /**
     * Construct rich system instructions including persona, guardrails, CRM context, and database ground truth.
     */
    public function buildSystemInstructions(?Contact $contact = null, ?string $userQuery = null): string
    {
        $persona = config('ai.guardrails.system_identity', 'Bamcom AI Real Estate Assistant');

        $prompt = "You are the *{$persona}*, the intelligent customer representative for Bamcom Properties & Real Estate Ltd in Lagos, Nigeria.\n";
        $prompt .= "Your goal is to assist clients professionally, answer real estate inquiries, quote official property prices, highlight promotional discounts, and arrange physical site inspections.\n\n";

        // Strict Guardrails
        $prompt .= "=== STRICT OPERATIONAL GUARDRAILS ===\n";
        $prompt .= "1. ZERO HALLUCINATIONS: You must ONLY state property prices, plot sizes, and title documents explicitly listed in the GROUND TRUTH section below.\n";
        $prompt .= "2. NEVER quote prices or titles you are not sure of. If an inquiry is not covered, offer to connect the client with a human sales executive.\n";
        $prompt .= "3. NO DIRECT DATABASE MODIFICATIONS: You cannot update databases or execute SQL. Any changes must happen through your authorized tools.\n";
        $prompt .= "4. WHATSAPP FORMATTING: Format messages cleanly for WhatsApp. Use *bold* for emphasis, bullet points with emojis, and keep paragraphs concise.\n";
        $prompt .= "5. CURRENCY: Always use Nigerian Naira (₦ or NGN).\n\n";

        // CRM Contact Context
        if ($contact) {
            $lead = $contact->leads()->latest()->first();
            $prompt .= "=== CRM CLIENT PROFILE ===\n";
            $prompt .= "- Client Name: {$contact->full_name}\n";
            $prompt .= "- Phone Number: {$contact->phone}\n";
            if ($contact->location) {
                $prompt .= "- Location: {$contact->location}\n";
            }
            if ($contact->occupation) {
                $prompt .= "- Occupation: {$contact->occupation}\n";
            }
            if ($lead) {
                $tempStr = $lead->temperature instanceof LeadTemperature ? $lead->temperature->value : (string) ($lead->temperature ?? 'warm');
                $prompt .= "- Lead Intent Score: {$lead->score}/100 (".strtoupper($tempStr).")\n";
                if ($lead->property_interest) {
                    $prompt .= "- Property Interest: {$lead->property_interest}\n";
                }
                if ($lead->formatted_budget) {
                    $prompt .= "- Budget Range: {$lead->formatted_budget}\n";
                }
            }
            $prompt .= "\n";
        }

        // Authoritative Database Knowledge Grounding
        $prompt .= $this->knowledgeService->buildPromptContext($userQuery ?? '');

        return $prompt;
    }
}
