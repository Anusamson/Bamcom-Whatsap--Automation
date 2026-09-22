<?php

namespace App\Enums;

/**
 * Expected acquisition or purchase timeframe for a lead opportunity.
 */
enum PurchaseTimeline: string
{
    case Immediate = 'immediate';
    case OneToThreeMonths = '1_3_months';
    case ThreeToSixMonths = '3_6_months';
    case SixToTwelveMonths = '6_12_months';
    case Flexible = 'flexible';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Immediate (0 - 30 Days)',
            self::OneToThreeMonths => '1 - 3 Months',
            self::ThreeToSixMonths => '3 - 6 Months',
            self::SixToTwelveMonths => '6 - 12 Months',
            self::Flexible => 'Flexible / Long-term',
        };
    }

    /**
     * All timeline values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
