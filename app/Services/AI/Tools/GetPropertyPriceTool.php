<?php

namespace App\Services\AI\Tools;

use App\Models\Property;

/**
 * Controlled Tool: getPropertyPrice
 *
 * Retrieves exact live pricing, promotional discounts, and required initial deposit.
 * Strictly prevents the AI from inventing or quoting unauthorized prices.
 */
class GetPropertyPriceTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'getPropertyPrice';
    }

    public function getDescription(): string
    {
        return 'Retrieve authoritative live pricing, promotional discounts, savings, and initial deposit for a property. Never invent prices.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'property_id' => [
                    'type' => 'integer',
                    'description' => 'The database ID of the property.',
                ],
                'property_name' => [
                    'type' => 'string',
                    'description' => 'Name or title of the property or estate.',
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Slug of the property.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $query = Property::query()->with(['estate', 'activePrice', 'promotion']);

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
                'error' => 'Please provide property_id, property_name, or slug to retrieve pricing.',
            ];
        }

        $property = $query->first();

        if (! $property) {
            return [
                'success' => true,
                'found' => false,
                'message' => 'Property not found in verified database. Inform the client that official pricing could not be located.',
            ];
        }

        $regular = (float) ($property->regular_price ?? 0);
        $promo = $property->promo_price !== null ? (float) $property->promo_price : null;
        $effective = (float) ($property->effective_price ?? $regular);
        $deposit = (float) ($property->initial_deposit ?? ($effective * 0.20));
        $savings = ($promo !== null && $regular > $effective) ? ($regular - $effective) : 0.0;

        return [
            'success' => true,
            'found' => true,
            'property_id' => $property->id,
            'title' => $property->title,
            'estate' => $property->estate?->name ?? 'Independent Scheme',
            'currency' => 'NGN (₦)',
            'regular_price' => $regular,
            'regular_price_formatted' => '₦'.number_format($regular, 2),
            'has_active_promo' => $promo !== null,
            'promo_price' => $promo,
            'promo_price_formatted' => $promo !== null ? '₦'.number_format($promo, 2) : null,
            'effective_price' => $effective,
            'effective_price_formatted' => '₦'.number_format($effective, 2),
            'savings' => $savings,
            'savings_formatted' => '₦'.number_format($savings, 2),
            'initial_deposit' => $deposit,
            'initial_deposit_formatted' => '₦'.number_format($deposit, 2),
            'payment_plan_summary' => $property->payment_plan_summary,
        ];
    }
}
