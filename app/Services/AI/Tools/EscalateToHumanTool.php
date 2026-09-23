<?php

namespace App\Services\AI\Tools;

use App\Enums\ConversationMode;
use App\Models\Conversation;
use InvalidArgumentException;

/**
 * Whitelisted tool to gracefully hand over conversation to a human representative.
 */
class EscalateToHumanTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'escalate_to_human';
    }

    public function getDescription(): string
    {
        return 'Hand over conversation from AI to human sales representative when requested by client or for complex inquiries.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => [
                    'type' => 'string',
                    'description' => 'Reason for escalating to a human (e.g. client requested human, negotiation, complex query)',
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        /** @var ?Conversation $conversation */
        $conversation = $context['conversation'] ?? null;
        if (! $conversation) {
            throw new InvalidArgumentException('Escalation requires an active conversation context.');
        }

        $reason = $arguments['reason'] ?? 'Customer requested human representative';

        // Switch mode to Human
        $conversation->update([
            'mode' => ConversationMode::Human,
            'metadata' => array_merge($conversation->metadata ?? [], [
                'escalated_at' => now()->toIso8601String(),
                'escalation_reason' => $reason,
            ]),
        ]);

        return [
            'success' => true,
            'mode' => 'human',
            'reason' => $reason,
            'message' => 'Conversation has been handed over to a human representative.',
        ];
    }
}
