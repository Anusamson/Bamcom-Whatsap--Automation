<?php

namespace App\Enums;

/**
 * Lifecycle statuses for CRM Email Marketing Campaigns.
 */
enum EmailCampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Sending = 'sending';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /**
     * Human-friendly label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Processing => 'Processing',
            self::Sending => 'Sending',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    /**
     * Tailwind CSS badge classes.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            self::Scheduled => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            self::Processing => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800 animate-pulse',
            self::Sending => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800 animate-pulse',
            self::Paused => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300 border-yellow-200 dark:border-yellow-800',
            self::Completed => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            self::Cancelled => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400 border-gray-200 dark:border-gray-700',
            self::Failed => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-200 dark:border-rose-800',
        };
    }

    /**
     * Whether the campaign can be modified.
     */
    public function canBeEdited(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }

    /**
     * Whether the campaign can be scheduled.
     */
    public function canBeScheduled(): bool
    {
        return in_array($this, [self::Draft, self::Paused], true);
    }

    /**
     * Whether the campaign can be launched or resumed.
     */
    public function canBeSent(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Paused], true);
    }

    /**
     * Whether the campaign can be paused.
     */
    public function canBePaused(): bool
    {
        return in_array($this, [self::Processing, self::Sending], true);
    }

    /**
     * Whether the campaign can be resumed.
     */
    public function canBeResumed(): bool
    {
        return $this === self::Paused;
    }

    /**
     * Whether the campaign can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Scheduled,
            self::Processing,
            self::Sending,
            self::Paused,
        ], true);
    }

    /**
     * All values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
