<?php

namespace App\Services\AI\Tools;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Enums\QualificationStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\Lead\LeadScoringService;

/**
 * Controlled Tool: updateLeadQualification
 *
 * Updates CRM lead temperature, budget, timeline, and property interest based on conversation cues.
 */
class UpdateLeadQualificationTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'updateLeadQualification';
    }

    public function getDescription(): string
    {
        return 'Update the client lead qualification in the CRM (temperature, budget, property interest, timeline).';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lead_id' => [
                    'type' => 'integer',
                    'description' => 'Optional ID of the lead to update.',
                ],
                'temperature' => [
                    'type' => 'string',
                    'enum' => ['hot', 'warm', 'cold'],
                    'description' => 'Lead intent temperature: "hot" (ready to buy/inspect), "warm" (inquiring), or "cold" (hesitant/unresponsive).',
                ],
                'property_interest' => [
                    'type' => 'string',
                    'description' => 'Specific estate or property scheme the client prefers (e.g., "Grace Haven Estate").',
                ],
                'budget_min' => [
                    'type' => 'number',
                    'description' => 'Minimum budget in NGN.',
                ],
                'budget_max' => [
                    'type' => 'number',
                    'description' => 'Maximum budget in NGN.',
                ],
                'purchase_timeline' => [
                    'type' => 'string',
                    'enum' => ['immediate', '1_3_months', '3_6_months', 'over_6_months'],
                    'description' => 'Timeline for purchasing.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Key insights learned from client conversation.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $lead = null;

        if (! empty($arguments['lead_id'])) {
            $lead = Lead::find((int) $arguments['lead_id']);
        }

        if (! $lead) {
            $contact = $context['contact'] ?? $context['conversation']?->contact;
            if ($contact instanceof Contact) {
                $lead = $contact->leads()->latest()->first();

                // Create lead if contact currently has none
                if (! $lead) {
                    $lead = Lead::create([
                        'contact_id' => $contact->id,
                        'title' => 'WhatsApp Lead: '.$contact->full_name,
                        'lead_source' => LeadSource::WhatsApp,
                        'status' => LeadStatus::New,
                        'temperature' => LeadTemperature::Warm,
                        'score' => 50,
                        'qualification_status' => QualificationStatus::InReview,
                    ]);
                }
            }
        }

        if (! $lead) {
            return [
                'success' => false,
                'error' => 'No active CRM lead found to update.',
            ];
        }

        $updates = [];
        $logDetails = [];

        if (! empty($arguments['temperature'])) {
            $temp = match (strtolower((string) $arguments['temperature'])) {
                'hot' => LeadTemperature::Hot,
                'cold' => LeadTemperature::Cold,
                default => LeadTemperature::Warm,
            };
            $updates['temperature'] = $temp;
            $logDetails['temperature'] = $temp->value;

            // Adjust score dynamically
            if ($temp === LeadTemperature::Hot && $lead->score < 80) {
                $updates['score'] = min(95, $lead->score + 25);
            }
        }

        if (! empty($arguments['property_interest'])) {
            $updates['property_interest'] = (string) $arguments['property_interest'];
            $logDetails['property_interest'] = $arguments['property_interest'];
        }

        if (isset($arguments['budget_min'])) {
            $updates['budget_min'] = (float) $arguments['budget_min'];
            $logDetails['budget_min'] = $arguments['budget_min'];
        }

        if (isset($arguments['budget_max'])) {
            $updates['budget_max'] = (float) $arguments['budget_max'];
            $logDetails['budget_max'] = $arguments['budget_max'];
        }

        if (! empty($arguments['purchase_timeline'])) {
            $timeline = match (strtolower((string) $arguments['purchase_timeline'])) {
                'immediate' => PurchaseTimeline::Immediate,
                '1_3_months', '1-3_months' => PurchaseTimeline::OneToThreeMonths,
                '3_6_months', '3-6_months' => PurchaseTimeline::ThreeToSixMonths,
                default => PurchaseTimeline::OverSixMonths,
            };
            $updates['purchase_timeline'] = $timeline;
            $logDetails['purchase_timeline'] = $timeline->value;
        }

        if (! empty($arguments['notes'])) {
            $existingNotes = $lead->notes ? $lead->notes."\n" : '';
            $updates['notes'] = $existingNotes.'[AI Note '.now()->format('Y-m-d H:i').']: '.$arguments['notes'];
            $logDetails['notes'] = $arguments['notes'];
        }

        if (! empty($updates)) {
            $lead->update($updates);

            // Trigger configurable scoring rules for newly supplied profile parameters
            app(LeadScoringService::class)->evaluateProfileEvents($lead);
            $lead->refresh();

            // Record CRM Activity log
            Activity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'activity_type' => 'qualification',
                'description' => 'AI updated lead qualification based on WhatsApp interaction.',
                'properties' => $logDetails,
            ]);
        }

        return [
            'success' => true,
            'lead_id' => $lead->id,
            'temperature' => $lead->temperature->value,
            'score' => (int) $lead->score,
            'property_interest' => $lead->property_interest,
            'budget_range' => $lead->formatted_budget,
            'message' => 'Lead qualification successfully updated in CRM.',
        ];
    }
}
