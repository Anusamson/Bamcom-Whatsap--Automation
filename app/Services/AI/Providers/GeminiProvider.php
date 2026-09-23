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
 * Google Gemini Model Provider.
 */
class GeminiProvider implements AIProviderInterface
{
    public function __construct(
        protected array $config = []
    ) {}

    public function generateResponse(array $messages, array $options = []): AIResponse
    {
        $apiKey = $this->config['api_key'] ?? '';
        $model = $this->config['model'] ?? 'gemini-1.5-flash';
        $baseUrl = rtrim($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta', '/');
        $timeout = (int) ($this->config['timeout'] ?? 20);

        if (empty($apiKey)) {
            throw new Exception('Google Gemini API key is not configured.');
        }

        // Convert messages to Gemini contents format
        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $text = $msg['content'] ?? '';

            if ($role === 'system') {
                $systemInstruction = [
                    'parts' => [['text' => $text]],
                ];
            } else {
                $contents[] = [
                    'role' => $role === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $text]],
                ];
            }
        }

        // Prepare tools if provided
        $tools = [];
        if (! empty($options['tools'])) {
            $declarations = [];
            foreach ($options['tools'] as $tool) {
                $declarations[] = [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
                ];
            }
            $tools[] = ['function_declarations' => $declarations];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? $this->config['temperature'] ?? 0.2),
                'maxOutputTokens' => (int) ($options['max_tokens'] ?? $this->config['max_tokens'] ?? 1024),
            ],
        ];

        if ($systemInstruction) {
            $payload['system_instruction'] = $systemInstruction;
        }

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        $url = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new Exception("Gemini API call failed: HTTP {$response->status()} - {$response->body()}");
        }

        $data = $response->json();
        $candidate = $data['candidates'][0] ?? null;
        $parts = $candidate['content']['parts'] ?? [];

        $responseText = '';
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $responseText .= $part['text'];
            }

            if (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'name' => $part['functionCall']['name'] ?? '',
                    'arguments' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        $usage = [
            'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
            'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            'total_tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0,
        ];

        $finishReason = ! empty($toolCalls) ? 'tool_calls' : ($candidate['finishReason'] ?? 'stop');

        return new AIResponse(
            content: trim($responseText),
            toolCalls: $toolCalls,
            usage: $usage,
            provider: 'gemini',
            finishReason: strtolower((string) $finishReason),
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
        // Strip markdown code fences if model returned ```json ... ```
        $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $rawText);
        $parsed = json_decode((string) $cleanJson, true);

        if (is_array($parsed) && isset($parsed['intent'])) {
            $intentEnum = AIIntent::tryFrom($parsed['intent']) ?? AIIntent::Unknown;

            return new IntentResult(
                intent: $intentEnum,
                confidence: (float) ($parsed['confidence'] ?? 0.9),
                entities: (array) ($parsed['entities'] ?? []),
                method: 'gemini'
            );
        }

        return new IntentResult(AIIntent::Unknown, 0.5, method: 'gemini_fallback');
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->config['api_key']);
    }
}
