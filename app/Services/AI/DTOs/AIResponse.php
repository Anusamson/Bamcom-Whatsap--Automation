<?php

namespace App\Services\AI\DTOs;

/**
 * Standardized AI model response container.
 */
class AIResponse
{
    /**
     * @param  string  $content  The generated response text
     * @param  array<int, array{name: string, arguments: array<string, mixed>}>  $toolCalls  Tool requests made by the model
     * @param  array<string, mixed>  $usage  Token usage statistics
     * @param  string  $provider  Provider name ('gemini', 'openai', 'mock')
     * @param  string  $finishReason  Stop reason ('stop', 'tool_calls', 'length')
     * @param  array<string, mixed>  $rawResponse  Raw response payload for debugging/audit
     */
    public function __construct(
        public string $content,
        public array $toolCalls = [],
        public array $usage = [],
        public string $provider = 'unknown',
        public string $finishReason = 'stop',
        public array $rawResponse = []
    ) {}

    /**
     * Check if the model requested one or more tool calls.
     */
    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    /**
     * Retrieve the first requested tool call, if any.
     *
     * @return ?array{name: string, arguments: array<string, mixed>}
     */
    public function firstToolCall(): ?array
    {
        return $this->toolCalls[0] ?? null;
    }

    /**
     * Serialize to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'tool_calls' => $this->toolCalls,
            'usage' => $this->usage,
            'provider' => $this->provider,
            'finish_reason' => $this->finishReason,
        ];
    }
}
