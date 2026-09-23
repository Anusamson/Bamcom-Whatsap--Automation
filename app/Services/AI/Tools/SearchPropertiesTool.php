<?php

namespace App\Services\AI\Tools;

use App\Services\Property\PropertyIntelligenceService;

/**
 * Controlled Tool: searchProperties
 *
 * Safely searches active real estate inventory in Bamcom's live database.
 * Strictly prevents the AI from inventing non-existent properties.
 */
class SearchPropertiesTool implements AIToolInterface
{
    public function __construct(
        protected PropertyIntelligenceService $propertyIntelligence
    ) {}

    public function getName(): string
    {
        return 'searchProperties';
    }

    public function getDescription(): string
    {
        return 'Search verified properties and estates in the live database matching location, price range, plot size, or property type.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'Target location, city, or estate name (e.g., "Ibeju-Lekki", "Epe", "Grace Haven").',
                ],
                'max_price' => [
                    'type' => 'number',
                    'description' => 'Maximum price in Nigerian Naira (NGN).',
                ],
                'min_price' => [
                    'type' => 'number',
                    'description' => 'Minimum price in Nigerian Naira (NGN).',
                ],
                'property_type' => [
                    'type' => 'string',
                    'description' => 'Type of property: "residential", "commercial", "agricultural", "industrial", or "land".',
                ],
                'plot_size' => [
                    'type' => 'string',
                    'description' => 'Target plot size (e.g., "500sqm", "600sqm", "300sqm", "1,000sqm").',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results to return (default: 5).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $criteria = [
            'location' => $arguments['location'] ?? null,
            'max_price' => isset($arguments['max_price']) ? (float) $arguments['max_price'] : null,
            'min_price' => isset($arguments['min_price']) ? (float) $arguments['min_price'] : null,
            'property_type' => $arguments['property_type'] ?? null,
            'plot_size' => $arguments['plot_size'] ?? null,
            'in_stock_only' => true,
            'limit' => isset($arguments['limit']) ? min((int) $arguments['limit'], 10) : 5,
        ];

        $results = $this->propertyIntelligence->queryPropertiesForAi($criteria);

        if ($results->isEmpty()) {
            return [
                'success' => true,
                'found_count' => 0,
                'properties' => [],
                'message' => 'No properties currently match those exact criteria. Advise client on available alternatives or ask to broaden search criteria.',
            ];
        }

        return [
            'success' => true,
            'found_count' => $results->count(),
            'properties' => $results->toArray(),
        ];
    }
}
