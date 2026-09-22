<?php

namespace App\Enums;

enum PropertyType: string
{
    case Land = 'land';
    case Residential = 'residential';
    case Commercial = 'commercial';
    case Duplex = 'duplex';
    case Terrace = 'terrace';
    case Apartment = 'apartment';

    public function label(): string
    {
        return match ($this) {
            self::Land => 'Dry Land / Plot',
            self::Residential => 'Residential Development',
            self::Commercial => 'Commercial Property',
            self::Duplex => 'Detached / Semi-Detached Duplex',
            self::Terrace => 'Terrace Duplex',
            self::Apartment => 'Luxury Apartment / Flat',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Land => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
            self::Residential => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-300 dark:border-blue-800',
            self::Commercial => 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border-purple-300 dark:border-purple-800',
            self::Duplex => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800',
            self::Terrace => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border-sky-300 dark:border-sky-800',
            self::Apartment => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-800',
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
