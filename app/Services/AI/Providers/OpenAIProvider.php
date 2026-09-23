<?php

namespace App\Services\AI\Providers;

use App\Enums\AIIntent;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\IntentResult;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI / ChatGPT Compatible Model Provider.
 */
class OpenAIProvider implements AIProviderInterface
{
    public function __construct(
        protected array $config = []
    ) {}

    public function generateResponse(array $messages, array $options = []): AIResponse
    {
        $apiKey = $this->config['api_key'] ?? '';
        $model = $this->config['model'] ?? 'gpt-4o-mini';
        $baseUrl = rtrim($this->config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $timeout = (int) ($this->config['timeout'] ?? 20);

        if (empty($apiKey)) {
            throw new Exception('OpenAI API key is not configured.');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float) ($options['temperature'] ?? $this->config['temperature'] ?? 0.2),
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->config['max_tokens'] ?? 1024),
        ];

        if (! empty($options['tools'])) {
            $formattedTools = [];
            foreach ($options['tools'] as $tool) {
                $formattedTools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                        'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
                    ],
                ];
            }
            $payload['tools'] = $formattedTools;
        }

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post("{$baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            Log::error('OpenAI API Error', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new Exception("OpenAI API call failed: HTTP {$response->status()} - {$response->body()}");
        }

        $data = $response->json();
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $toolCalls = [];
        if (! empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tc) {
                $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
                $toolCalls[] = [
                    'name' => $tc['function']['name'] ?? '',
                    'arguments' => $args,
                ];
            }
        }

        $usage = [
            'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $data['usage']['total_tokens'] ?? 0,
        ];

        return new AIResponse(
            content: trim((string) ($message['content'] ?? '')),
            toolCalls: $toolCalls,
            usage: $usage,
            provider: 'openai',
            finishReason: $choice['finish_reason'] ?? 'stop',
            rawResponse: $data
        );
    }

    public function classifyIntent(string $message, array $candidateIntents = []): IntentResult
    {
        $systemPrompt = "You are a real estate query classification system for Bamcom Properties in Nigeria.\n"
            ."Classify the user message into one of these intents: property_inquiry, pricing_inquiry, inspection_booking, title_verification, human_handover, general_faq, greeting, unknown.\n"
            .'Return strictly valid JSON with keys: "intent", "confidence" (float 0.0-1.0), and "entities" (object with any extracted location, budget, estate, plot_size).';

        $response = $this->generateResponse([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ], ['temperature' => 0.0]);

        $rawText = trim($response->content);
        $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $rawText);
        $parsed = json_decode((string) $cleanJson, true);

        if (is_array($parsed) && isset($parsed['intent'])) {
            $intentEnum = AIIntent::tryFrom($parsed['intent']) ?? AIIntent::Unknown;

            return new IntentResult(
                intent: $intentEnum,
                confidence: (float) ($parsed['confidence'] ?? 0.9),
                entities: (array) ($parsed['entities'] ?? []),
                method: 'openai'
            );
        }

        return new IntentResult(AIIntent::Unknown, 0.5, method: 'openai_fallback');
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->config['api_key']);
    }
}
