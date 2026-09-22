<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case UnderOffer = 'under_offer';
    case SoldOut = 'sold_out';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available for Purchase',
            self::Reserved => 'Reserved by Buyer',
            self::UnderOffer => 'Under Offer / In Review',
            self::SoldOut => 'Completely Sold Out',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Available => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
            self::Reserved => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-800',
            self::UnderOffer => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800',
            self::SoldOut => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-300 dark:border-rose-800',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
