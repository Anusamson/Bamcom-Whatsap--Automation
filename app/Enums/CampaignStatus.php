<?php

namespace App\Enums;

/**
 * Status of a WhatsApp marketing or nurture campaign.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Running => 'Running',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border-slate-300',
            self::Scheduled => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-300',
            self::Running => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-300 animate-pulse',
            self::Paused => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-300',
            self::Completed => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border-purple-300',
            self::Cancelled => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-300',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'blue',
            self::Running => 'green',
            self::Paused => 'amber',
            self::Completed => 'purple',
            self::Cancelled => 'red',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function canBeStarted(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }

    public function canBePaused(): bool
    {
        return $this === self::Running;
    }

    public function canBeResumed(): bool
    {
        return $this === self::Paused;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Running, self::Paused], true);
    }
}
