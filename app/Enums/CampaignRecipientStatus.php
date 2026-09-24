<?php

namespace App\Enums;

/**
 * Status of an individual recipient inside a WhatsApp campaign.
 */
enum CampaignRecipientStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case OptedOut = 'opted_out';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
            self::OptedOut => 'Opted Out',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Queued => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            self::Sent => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            self::Delivered => 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300',
            self::Read => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
            self::Failed => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
            self::Skipped => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            self::OptedOut => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::Sent, self::Delivered, self::Read], true);
    }
}
