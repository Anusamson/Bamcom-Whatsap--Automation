<?php

namespace App\Services\AI\Tools;

use App\Models\Activity;
use App\Models\Conversation;
use App\Models\Estate;
use App\Models\Lead;
use InvalidArgumentException;

/**
 * Whitelisted tool to schedule estate site inspections for contacts.
 */
class BookInspectionTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'book_inspection';
    }

    public function getDescription(): string
    {
        return 'Schedule a physical site inspection for a prospective buyer to visit a Bamcom estate.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['estate_name', 'date', 'time'],
            'properties' => [
                'estate_name' => [
                    'type' => 'string',
                    'description' => 'Name of the estate to inspect (e.g. Oasis Heights Estate, Grandview Meadows)',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Date of inspection in YYYY-MM-DD format (Monday to Saturday)',
                ],
                'time' => [
                    'type' => 'string',
                    'description' => 'Time of inspection (e.g. 10:00 AM or 2:00 PM)',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Any special client requests or pickup instructions',
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $estateName = trim((string) ($arguments['estate_name'] ?? ''));
        $date = trim((string) ($arguments['date'] ?? ''));
        $time = trim((string) ($arguments['time'] ?? '10:00 AM'));
        $notes = trim((string) ($arguments['notes'] ?? 'Booked via AI WhatsApp Assistant'));

        if (empty($estateName) || empty($date)) {
            throw new InvalidArgumentException('Both estate_name and date are required to schedule an inspection.');
        }

        /** @var ?Conversation $conversation */
        $conversation = $context['conversation'] ?? null;
        if (! $conversation) {
            throw new InvalidArgumentException('Inspection booking requires an active conversation context.');
        }

        $contact = $conversation->contact;
        $lead = $contact->leads()->latest()->first();

        if (! $lead) {
            $lead = Lead::create([
                'contact_id' => $contact->id,
                'assigned_user_id' => $conversation->assigned_user_id,
                'title' => "Inspection Lead: {$estateName}",
                'lead_source' => $contact->lead_source,
                'property_interest' => $estateName,
                'notes' => 'Initiated during WhatsApp inquiry.',
            ]);
        }

        // Validate estate exists or matches closely
        $estate = Estate::where('name', 'like', "%{$estateName}%")->first();
        $verifiedEstateName = $estate ? $estate->name : $estateName;

        $activity = Activity::create([
            'user_id' => $conversation->assigned_user_id,
            'lead_id' => $lead->id,
            'activity_type' => 'inspection_scheduled',
            'description' => "AI scheduled site inspection for {$contact->full_name} at {$verifiedEstateName} on {$date} at {$time}.",
            'properties' => [
                'estate_name' => $verifiedEstateName,
                'inspection_date' => $date,
                'inspection_time' => $time,
                'notes' => $notes,
                'scheduled_by' => 'Bamcom AI Assistant',
                'status' => 'scheduled',
            ],
        ]);

        return [
            'success' => true,
            'booking_id' => $activity->id,
            'estate_name' => $verifiedEstateName,
            'date' => $date,
            'time' => $time,
            'message' => "Inspection confirmed for {$verifiedEstateName} on {$date} at {$time}.",
        ];
    }
}
