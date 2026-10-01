<?php

namespace App\Enums;

/**
 * Root cause justification for email suppression.
 */
enum EmailSuppressionReason: string
{
    case Bounce = 'bounce';
    case Complaint = 'complaint';
    case Unsubscribe = 'unsubscribe';
    case Manual = 'manual';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Bounce => 'Hard Bounce',
            self::Complaint => 'Spam Complaint',
            self::Unsubscribe => 'Unsubscribed',
            self::Manual => 'Manual Suppression',
        };
    }

    /**
     * Tailwind CSS badge styling.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Bounce => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            self::Complaint => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 border-red-200 dark:border-red-800',
            self::Unsubscribe => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::Manual => 'bg-slate-100 text-slate-800 dark:bg-slate-800/60 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }

    /**
     * All enum values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
