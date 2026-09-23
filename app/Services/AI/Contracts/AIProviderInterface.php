<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\IntentResult;

/**
 * Provider-agnostic interface for AI models and LLM providers.
 */
interface AIProviderInterface
{
    /**
     * Generate response for conversation messages with optional tool definitions.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options  Model options (temperature, tools, max_tokens)
     */
    public function generateResponse(array $messages, array $options = []): AIResponse;

    /**
     * Classify user query into standardized real estate intent.
     *
     * @param  string  $message  User message
     * @param  array<int, string>  $candidateIntents  List of supported intent values
     */
    public function classifyIntent(string $message, array $candidateIntents = []): IntentResult;

    /**
     * Retrieve the unique name of the provider implementation.
     */
    public function getProviderName(): string;

    /**
     * Check if the provider is fully configured with valid credentials.
     */
    public function isAvailable(): bool;
}
