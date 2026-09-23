<?php

namespace App\Services\AI\Tools;

use App\Services\Property\PropertyIntelligenceService;

/**
 * Whitelisted tool to query authoritative property inventory by criteria.
 */
class SearchInventoryTool implements AIToolInterface
{
    public function __construct(
        protected PropertyIntelligenceService $propertyIntelligence
    ) {}

    public function getName(): string
    {
        return 'search_inventory';
    }

    public function getDescription(): string
    {
        return 'Search verified Bamcom estate plots and houses by location, price range, property type, or estate name.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'Target area or city (e.g. Epe, Lekki, Ibeju-Lekki, Ikoyi)',
                ],
                'property_type' => [
                    'type' => 'string',
                    'description' => 'Type of property: residential_land, commercial_land, duplex, bungalow, terrace',
                ],
                'max_price' => [
                    'type' => 'number',
                    'description' => 'Maximum price in Nigerian Naira (NGN)',
                ],
                'min_price' => [
                    'type' => 'number',
                    'description' => 'Minimum price in Nigerian Naira (NGN)',
                ],
                'in_stock_only' => [
                    'type' => 'boolean',
                    'description' => 'Whether to restrict results to units currently available in stock (default: true)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of items to return (max: 5)',
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $criteria = [
            'location' => $arguments['location'] ?? null,
            'property_type' => $arguments['property_type'] ?? null,
            'max_price' => isset($arguments['max_price']) ? (float) $arguments['max_price'] : null,
            'min_price' => isset($arguments['min_price']) ? (float) $arguments['min_price'] : null,
            'in_stock_only' => $arguments['in_stock_only'] ?? true,
            'limit' => min((int) ($arguments['limit'] ?? 3), 5),
        ];

        $results = $this->propertyIntelligence->queryPropertiesForAi($criteria);

        return [
            'success' => true,
            'count' => $results->count(),
            'properties' => $results->map(function ($item): array {
                return [
                    'title' => $item['title'],
                    'estate' => $item['estate_name'],
                    'location' => $item['location'],
                    'plot_size' => $item['plot_size'],
                    'title_document' => $item['title_document'],
                    'effective_price_formatted' => $item['pricing']['effective_price_formatted'],
                    'initial_deposit_formatted' => $item['pricing']['initial_deposit_formatted'],
                    'has_active_promo' => $item['pricing']['has_active_promo'],
                    'available_units' => $item['available_units'],
                    'payment_plan' => $item['payment_plan_summary'],
                ];
            })->toArray(),
        ];
    }
}
