<?php

namespace App\Services\AI\Tools;

/**
 * Contract for explicitly defined application tools executable by AI models.
 */
interface AIToolInterface
{
    /**
     * Unique identifier of the tool (e.g. 'search_inventory', 'book_inspection').
     */
    public function getName(): string;

    /**
     * Clear, descriptive summary of what the tool accomplishes.
     */
    public function getDescription(): string;

    /**
     * JSON Schema / Array schema for tool arguments.
     *
     * @return array<string, mixed>
     */
    public function getParametersSchema(): array;

    /**
     * Safely execute the application service action with validated arguments.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context  Caller context (conversation, contact, user)
     * @return array<string, mixed> Execution result
     */
    public function execute(array $arguments, array $context = []): array;
}
