<?php

namespace App\Services\AI\Tools;

use App\Enums\ConversationMode;
use App\Models\Activity;
use App\Models\Conversation;
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
            $conversation->update([
                'mode' => ConversationMode::Human,
                'status' => 'open',
            ]);

            $lead = $conversation->contact?->leads()->latest()->first();

            Activity::create([
                'lead_id' => $lead?->id,
                'user_id' => $conversation->assigned_user_id,
                'activity_type' => 'escalation',
                'description' => "AI escalated conversation to human representative. Reason: {$reason}",
                'properties' => [
                    'reason' => $reason,
                    'urgency' => $urgency,
                    'conversation_id' => $conversation->id,
                    'escalated_at' => now()->toIso8601String(),
                ],
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
