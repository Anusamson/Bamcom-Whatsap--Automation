<?php

namespace App\Services\AI\Tools;

use App\Models\Property;
use App\Services\Property\PropertyIntelligenceService;

/**
 * Controlled Tool: getPropertyDetails
 *
 * Retrieves verified specifications, landmarks, legal title, and features of a specific property.
 * Strictly prevents the AI from inventing features or title documents.
 */
class GetPropertyDetailsTool implements AIToolInterface
{
    public function __construct(
        protected PropertyIntelligenceService $propertyIntelligence
    ) {}

    public function getName(): string
    {
        return 'getPropertyDetails';
    }

    public function getDescription(): string
    {
        return 'Retrieve complete verified details, landmarks, legal land title, features, and estate background for a specific property.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'property_id' => [
                    'type' => 'integer',
                    'description' => 'The unique database ID of the property.',
                ],
                'property_name' => [
                    'type' => 'string',
                    'description' => 'Name or title of the property or estate (e.g. "Grace Haven", "Atlantic Bay").',
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'URL slug of the property.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $query = Property::query()
            ->with(['estate', 'activePrice', 'promotion', 'primaryMedia']);

        if (! empty($arguments['property_id'])) {
            $query->where('id', (int) $arguments['property_id']);
        } elseif (! empty($arguments['slug'])) {
            $query->where('slug', (string) $arguments['slug']);
        } elseif (! empty($arguments['property_name'])) {
            $name = trim((string) $arguments['property_name']);
            $query->where(function ($q) use ($name): void {
                $q->where('title', 'like', "%{$name}%")
                    ->orWhereHas('estate', fn ($eq) => $eq->where('name', 'like', "%{$name}%"));
            });
        } else {
            return [
                'success' => false,
                'found' => false,
                'error' => 'Please provide property_id, property_name, or slug to retrieve details.',
            ];
        }

        $property = $query->first();

        if (! $property) {
            return [
                'success' => true,
                'found' => false,
                'message' => 'Property not found in verified database. Inform the client politely that this property could not be found and offer to check other available estates.',
            ];
        }

        return [
            'success' => true,
            'found' => true,
            'property' => $this->propertyIntelligence->formatPropertyForAi($property),
        ];
    }
}
