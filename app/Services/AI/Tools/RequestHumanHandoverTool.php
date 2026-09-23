<?php

namespace App\Services\AI\Tools;

use App\Enums\HandoverTrigger;
use App\Models\Conversation;
use App\Services\AI\HandoverService;
use Illuminate\Support\Facades\Log;

/**
 * Controlled Tool: requestHumanHandover
 *
 * Transitions the conversation mode to Human for agent takeover and alerts sales reps.
 */
class RequestHumanHandoverTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'requestHumanHandover';
    }

    public function getDescription(): string
    {
        return 'Request human representative takeover when a client explicitly requests a person, needs complex legal assistance, or requires closing negotiations.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => [
                    'type' => 'string',
                    'description' => 'Specific reason for escalation (e.g., "Client requested a call with manager", "Complex legal question").',
                ],
                'urgency' => [
                    'type' => 'string',
                    'enum' => ['low', 'medium', 'high'],
                    'description' => 'Escalation urgency level (default: "medium").',
                ],
            ],
            'required' => ['reason'],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $conversation = $context['conversation'] ?? null;
        $reason = (string) ($arguments['reason'] ?? 'Customer requested human assistance');
        $urgency = (string) ($arguments['urgency'] ?? 'medium');

        if ($conversation instanceof Conversation) {
            /** @var HandoverService $handoverService */
            $handoverService = app(HandoverService::class);
            $handoverService->executeHandover($conversation, HandoverTrigger::CustomerRequest, [
                'reason' => $reason,
                'urgency' => $urgency,
            ]);

            Log::info("Conversation #{$conversation->id} switched to human mode via RequestHumanHandoverTool.", [
                'reason' => $reason,
                'urgency' => $urgency,
            ]);
        }

        return [
            'success' => true,
            'conversation_mode' => 'human',
            'urgency' => $urgency,
            'message' => 'Conversation has been transitioned to a human sales representative. Reassure the client that a team member will follow up shortly.',
        ];
    }
}
