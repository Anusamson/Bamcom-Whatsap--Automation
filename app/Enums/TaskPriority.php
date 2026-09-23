<?php

namespace App\Enums;

/**
 * Priority levels of a CRM task.
 */
enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    /**
     * Tailwind badge styling.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            self::Medium => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            self::High => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::Urgent => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-200 dark:border-rose-800 font-bold',
        };
    }

    /**
     * Semantic color string.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'slate',
            self::Medium => 'blue',
            self::High => 'amber',
            self::Urgent => 'rose',
        };
    }

    /**
     * Numeric weight for sorting.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }

    /**
     * All valid priority values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
