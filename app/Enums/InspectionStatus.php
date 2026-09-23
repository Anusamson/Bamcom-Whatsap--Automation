<?php

namespace App\Enums;

/**
 * Statuses of a site inspection lifecycle.
 */
enum InspectionStatus: string
{
    case Requested = 'requested';
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
    case NoShow = 'no-show';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Scheduled => 'Scheduled',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Rescheduled => 'Rescheduled',
            self::NoShow => 'No-Show',
        };
    }

    /**
     * Badge CSS styling for Tailwind.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Requested => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::Scheduled => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            self::Confirmed => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            self::Completed => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            self::Cancelled => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            self::Rescheduled => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            self::NoShow => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }

    /**
     * Indicator color string.
     */
    public function color(): string
    {
        return match ($this) {
            self::Requested => 'amber',
            self::Scheduled => 'blue',
            self::Confirmed => 'indigo',
            self::Completed => 'emerald',
            self::Cancelled => 'rose',
            self::Rescheduled => 'purple',
            self::NoShow => 'slate',
        };
    }

    /**
     * Does this status represent an active reservation that occupies a representative's schedule?
     */
    public function canConflict(): bool
    {
        return in_array($this, [
            self::Requested,
            self::Scheduled,
            self::Confirmed,
            self::Rescheduled,
        ], true);
    }

    /**
     * Is this status terminal (inspection finished or aborted)?
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Cancelled,
            self::NoShow,
        ], true);
    }

    /**
     * All valid status values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
