<?php

namespace App\Services\AI\Tools;

use App\Enums\LeadTemperature;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Property;
use App\Services\Lead\LeadScoringService;

/**
 * Controlled Tool: scheduleInspection
 *
 * Books physical site inspections, schedules transport, and creates CRM activity records.
 */
class ScheduleInspectionTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'scheduleInspection';
    }

    public function getDescription(): string
    {
        return 'Schedule a physical site inspection for a client to visit an estate development in Lagos/Ogun state.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'preferred_date' => [
                    'type' => 'string',
                    'description' => 'Target inspection date in YYYY-MM-DD format (Inspections run Monday through Saturday).',
                ],
                'preferred_time' => [
                    'type' => 'string',
                    'description' => 'Preferred time session: "10:00 AM" (morning session) or "02:00 PM" (afternoon session).',
                ],
                'property_id' => [
                    'type' => 'integer',
                    'description' => 'Optional database ID of the property or estate to be inspected.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Special requests, pickup instructions, or number of attendees.',
                ],
            ],
            'required' => ['preferred_date'],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $contact = $context['contact'] ?? $context['conversation']?->contact;
        $date = trim((string) ($arguments['preferred_date'] ?? ''));
        $time = ! empty($arguments['preferred_time']) ? trim((string) $arguments['preferred_time']) : '10:00 AM';

        if (empty($date)) {
            return [
                'success' => false,
                'error' => 'A valid inspection date is required.',
            ];
        }

        // Fetch property/estate name if property_id passed
        $propertyTitle = null;
        if (! empty($arguments['property_id'])) {
            $prop = Property::query()->with('estate')->find((int) $arguments['property_id']);
            if ($prop) {
                $propertyTitle = $prop->title.' ('.$prop->estate?->name.')';
            }
        }

        $lead = $contact instanceof Contact ? $contact->leads()->latest()->first() : null;

        // Award lead scoring points for site inspection request
        if ($lead) {
            app(LeadScoringService::class)->recordEvent(
                $lead,
                'inspection_request',
                ['date' => $date, 'time' => $time, 'property' => $propertyTitle],
                source: 'ai_agent'
            );

            $lead->refresh();
            if ($lead->temperature !== LeadTemperature::Hot) {
                $lead->update([
                    'temperature' => LeadTemperature::Hot,
                    'score' => max(60, (int) $lead->score),
                ]);
            }
        }

        // Record scheduled inspection activity in CRM
        $activity = Activity::create([
            'lead_id' => $lead?->id,
            'user_id' => auth()->id(),
            'activity_type' => 'inspection',
            'description' => "Physical site inspection scheduled for {$date} at {$time}.".($propertyTitle ? " Property: {$propertyTitle}." : ''),
            'properties' => [
                'preferred_date' => $date,
                'preferred_time' => $time,
                'property_id' => $arguments['property_id'] ?? null,
                'property_title' => $propertyTitle,
                'pickup_location' => 'Plot 12, Admiralty Way, Lekki Phase 1, Lagos',
                'notes' => $arguments['notes'] ?? null,
                'booked_via' => 'BamcomSalesAgent',
            ],
        ]);

        return [
            'success' => true,
            'inspection_id' => $activity->id,
            'preferred_date' => $date,
            'preferred_time' => $time,
            'property_title' => $propertyTitle,
            'pickup_location' => 'Bamcom Corporate Office, Plot 12, Admiralty Way, Lekki Phase 1, Lagos',
            'inspection_vehicle' => 'Air-conditioned company escort vehicle provided free of charge',
            'message' => "Inspection scheduled successfully for {$date} at {$time}. Ask the client to arrive 15 minutes before departure at Lekki Phase 1.",
        ];
    }
}
