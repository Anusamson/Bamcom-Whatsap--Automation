<?php

namespace App\Services\AI\Tools;

use App\Models\Property;

/**
 * Whitelisted tool to compute installment and deposit spreads accurately.
 */
class CalculatePaymentPlanTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'calculate_payment_plan';
    }

    public function getDescription(): string
    {
        return 'Calculate the deposit and monthly payment spread for a property based on verified pricing.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['property_id', 'duration_months'],
            'properties' => [
                'property_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the property',
                ],
                'duration_months' => [
                    'type' => 'integer',
                    'description' => 'Number of months to spread payments across (e.g. 3, 6, 12)',
                ],
                'custom_deposit' => [
                    'type' => 'number',
                    'description' => 'Optional custom initial deposit amount in NGN',
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $propertyId = (int) ($arguments['property_id'] ?? 0);
        $duration = max(1, min((int) ($arguments['duration_months'] ?? 6), 24));

        $property = Property::with('activePrice')->find($propertyId);
        if (! $property) {
            return [
                'success' => false,
                'error' => "Property #{$propertyId} not found.",
            ];
        }

        $totalPrice = $property->effective_price ?? ($property->regular_price ?? 0);
        $standardDeposit = $property->initial_deposit ?? ($totalPrice * 0.20);
        $deposit = isset($arguments['custom_deposit']) ? (float) $arguments['custom_deposit'] : $standardDeposit;

        $remaining = max(0, $totalPrice - $deposit);
        $monthlyPayment = $duration > 0 ? ($remaining / $duration) : 0;

        return [
            'success' => true,
            'property_title' => $property->title,
            'total_price' => $totalPrice,
            'total_price_formatted' => '₦'.number_format($totalPrice, 2),
            'initial_deposit' => $deposit,
            'initial_deposit_formatted' => '₦'.number_format($deposit, 2),
            'remaining_balance' => $remaining,
            'remaining_balance_formatted' => '₦'.number_format($remaining, 2),
            'duration_months' => $duration,
            'monthly_installment_formatted' => '₦'.number_format($monthlyPayment, 2),
        ];
    }
}
