<?php

namespace App\Services\AI\Tools;

use App\Models\Estate;
use App\Models\Property;

/**
 * Controlled Tool: checkAvailability
 *
 * Verifies live inventory unit counts and availability status in the database.
 * Strictly prevents the AI from inventing unit availability.
 */
class CheckAvailabilityTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'checkAvailability';
    }

    public function getDescription(): string
    {
        return 'Check the live availability and remaining units for a property or estate development.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'property_id' => [
                    'type' => 'integer',
                    'description' => 'Optional database ID of the property.',
                ],
                'estate_id' => [
                    'type' => 'integer',
                    'description' => 'Optional database ID of the estate development.',
                ],
                'property_name' => [
                    'type' => 'string',
                    'description' => 'Name or title of the property or estate.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        // 1. Check by Property ID or Name
        if (! empty($arguments['property_id']) || ! empty($arguments['property_name'])) {
            $query = Property::query()->with('estate');

            if (! empty($arguments['property_id'])) {
                $query->where('id', (int) $arguments['property_id']);
            } else {
                $name = trim((string) $arguments['property_name']);
                $query->where('title', 'like', "%{$name}%")
                    ->orWhereHas('estate', fn ($eq) => $eq->where('name', 'like', "%{$name}%"));
            }

            $property = $query->first();

            if (! $property) {
                return [
                    'success' => true,
                    'found' => false,
                    'message' => 'Property could not be located in the database. Please verify the name or offer to search available estates.',
                ];
            }

            $isAvailable = $property->available_units > 0 && $property->availability === 'available';

            return [
                'success' => true,
                'found' => true,
                'property_id' => $property->id,
                'title' => $property->title,
                'estate_name' => $property->estate?->name ?? 'Independent Scheme',
                'is_available' => $isAvailable,
                'availability_status' => $property->availability,
                'available_units' => (int) $property->available_units,
                'total_units' => (int) $property->total_units,
                'message' => $isAvailable
                    ? "Verified available: {$property->available_units} units remaining out of {$property->total_units} total."
                    : 'This property is currently sold out or reserved. Propose alternative inventory to the client.',
            ];
        }

        // 2. Check by Estate ID
        if (! empty($arguments['estate_id'])) {
            $estate = Estate::query()->withCount('properties')->find((int) $arguments['estate_id']);

            if (! $estate) {
                return [
                    'success' => true,
                    'found' => false,
                    'message' => 'Estate not found in database.',
                ];
            }

            $availableUnits = Property::query()
                ->where('estate_id', $estate->id)
                ->where('availability', 'available')
                ->sum('available_units');

            return [
                'success' => true,
                'found' => true,
                'estate_id' => $estate->id,
                'estate_name' => $estate->name,
                'location' => $estate->location,
                'is_available' => $availableUnits > 0,
                'total_plots_available' => (int) $availableUnits,
                'active_property_schemes' => (int) $estate->properties_count,
            ];
        }

        return [
            'success' => false,
            'error' => 'Please provide property_id, estate_id, or property_name to verify availability.',
        ];
    }
}
