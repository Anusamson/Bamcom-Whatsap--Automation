<?php

namespace App\Enums;

/**
 * Temperature of a sales opportunity representing purchase urgency and readiness.
 */
enum LeadTemperature: string
{
    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hot => 'Hot Lead',
            self::Warm => 'Warm Lead',
            self::Cold => 'Cold Lead',
        };
    }

    /**
     * Visual icon indicator.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Hot => '🔥',
            self::Warm => '☀️',
            self::Cold => '❄️',
        };
    }

    /**
     * Tailwind CSS badge styling.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Hot => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 border-red-200 dark:border-red-800',
            self::Warm => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::Cold => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300 border-sky-200 dark:border-sky-800',
        };
    }

    /**
     * All temperature values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
