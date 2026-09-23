<?php

namespace App\Services\AI\Tools;

use App\Models\Property;

/**
 * Controlled Tool: getPaymentPlan
 *
 * Calculates structured installment breakdowns for prospective buyers.
 * Explains deposit requirements and monthly spreads without hidden fees.
 */
class GetPaymentPlanTool implements AIToolInterface
{
    public function getName(): string
    {
        return 'getPaymentPlan';
    }

    public function getDescription(): string
    {
        return 'Calculate structured installment spreads (3, 6, or 12 months) and deposit milestones for a property or price.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'property_id' => [
                    'type' => 'integer',
                    'description' => 'Optional database ID of the property to calculate for.',
                ],
                'total_amount' => [
                    'type' => 'number',
                    'description' => 'Total property price in NGN (if property_id is not provided).',
                ],
                'initial_deposit' => [
                    'type' => 'number',
                    'description' => 'Custom initial deposit amount in NGN (defaults to 20% of price).',
                ],
                'duration_months' => [
                    'type' => 'integer',
                    'description' => 'Spread duration in months: 3, 6, or 12 (default: 6).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $totalAmount = 0.0;
        $propertyTitle = null;

        if (! empty($arguments['property_id'])) {
            $property = Property::query()->with('activePrice')->find((int) $arguments['property_id']);
            if ($property) {
                $totalAmount = (float) ($property->effective_price ?? $property->regular_price ?? 0);
                $propertyTitle = $property->title;
            }
        }

        if ($totalAmount <= 0 && ! empty($arguments['total_amount'])) {
            $totalAmount = (float) $arguments['total_amount'];
        }

        if ($totalAmount <= 0) {
            return [
                'success' => false,
                'error' => 'Please provide a valid property_id or total_amount to calculate the payment plan.',
            ];
        }

        $durationMonths = isset($arguments['duration_months']) ? (int) $arguments['duration_months'] : 6;
        if (! in_array($durationMonths, [3, 6, 12])) {
            $durationMonths = 6;
        }

        // Default initial deposit: 20% to 30%
        $minDeposit = $totalAmount * 0.20;
        $deposit = isset($arguments['initial_deposit']) && (float) $arguments['initial_deposit'] >= $minDeposit
            ? (float) $arguments['initial_deposit']
            : $minDeposit;

        $remainingBalance = max(0.0, $totalAmount - $deposit);
        $monthlyPayment = $remainingBalance > 0 ? ($remainingBalance / $durationMonths) : 0.0;

        return [
            'success' => true,
            'property_title' => $propertyTitle,
            'currency' => 'NGN (₦)',
            'total_amount' => $totalAmount,
            'total_amount_formatted' => '₦'.number_format($totalAmount, 2),
            'initial_deposit' => $deposit,
            'initial_deposit_formatted' => '₦'.number_format($deposit, 2),
            'deposit_percentage' => round(($deposit / $totalAmount) * 100, 1).'%',
            'duration_months' => $durationMonths,
            'monthly_payment' => $monthlyPayment,
            'monthly_payment_formatted' => '₦'.number_format($monthlyPayment, 2),
            'interest_rate' => '0% (Interest-Free Promotional Spread)',
            'milestones' => [
                'Deposit' => 'Official Receipt & Contract of Sale / Provisional Allocation Letter',
                '50% Payment' => 'Plot Reservation & Layout Coordinates Identification',
                '100% Completion' => 'Deed of Assignment, Registered Survey, and Physical Site Handover',
            ],
        ];
    }
}
