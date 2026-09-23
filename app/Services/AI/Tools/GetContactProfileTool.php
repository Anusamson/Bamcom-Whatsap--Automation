<?php

namespace App\Services\AI\Tools;

use App\Enums\LeadTemperature;
use App\Models\Contact;

/**
 * Controlled Tool: getContactProfile
 *
 * Retrieves CRM contact profile, lead score, temperature, and buyer preferences.
 */
class GetContactProfileTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'getContactProfile';
    }

    public function getDescription(): string
    {
        return 'Retrieve CRM contact profile, buyer score, temperature, budget, and property interest for the current client.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'contact_id' => [
                    'type' => 'integer',
                    'description' => 'Optional database ID of the contact.',
                ],
                'phone' => [
                    'type' => 'string',
                    'description' => 'Optional phone number of the contact.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $contact = null;

        if (! empty($arguments['contact_id'])) {
            $contact = Contact::query()->with('leads')->find((int) $arguments['contact_id']);
        } elseif (! empty($arguments['phone'])) {
            $phone = trim((string) $arguments['phone']);
            $contact = Contact::query()->with('leads')->where('phone', $phone)->first();
        } elseif (! empty($context['contact']) && $context['contact'] instanceof Contact) {
            $contact = $context['contact'];
        } elseif (! empty($context['conversation']) && $context['conversation']->contact) {
            $contact = $context['conversation']->contact;
        }

        if (! $contact) {
            return [
                'success' => false,
                'message' => 'Client contact profile could not be identified.',
            ];
        }

        $lead = $contact->leads()->latest()->first();
        $tempStr = $lead?->temperature instanceof LeadTemperature
            ? $lead->temperature->value
            : ($lead?->temperature ?? 'warm');

        return [
            'success' => true,
            'contact_id' => $contact->id,
            'name' => $contact->full_name,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'location' => $contact->location,
            'occupation' => $contact->occupation,
            'lead' => $lead ? [
                'lead_id' => $lead->id,
                'score' => (int) $lead->score,
                'temperature' => strtoupper($tempStr),
                'property_interest' => $lead->property_interest,
                'budget_range' => $lead->formatted_budget,
                'purchase_timeline' => $lead->purchase_timeline?->label() ?? 'Flexible',
                'notes' => $lead->notes,
            ] : null,
        ];
    }
}
