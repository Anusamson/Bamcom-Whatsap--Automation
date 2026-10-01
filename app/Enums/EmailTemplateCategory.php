<?php

namespace App\Enums;

/**
 * Functional categories for CRM email templates.
 */
enum EmailTemplateCategory: string
{
    case Marketing = 'marketing';
    case Property = 'property';
    case Welcome = 'welcome';
    case FollowUp = 'follow_up';
    case Inspection = 'inspection';
    case Payment = 'payment';
    case Newsletter = 'newsletter';
    case Promotion = 'promotion';
    case Transactional = 'transactional';
    case ReEngagement = 're_engagement';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Marketing => 'Marketing',
            self::Property => 'Property',
            self::Welcome => 'Welcome',
            self::FollowUp => 'Follow-up',
            self::Inspection => 'Inspection',
            self::Payment => 'Payment',
            self::Newsletter => 'Newsletter',
            self::Promotion => 'Promotion',
            self::Transactional => 'Transactional',
            self::ReEngagement => 'Re-engagement',
        };
    }

    /**
     * Whether templates in this category require an unsubscribe link (compliance).
     */
    public function requiresUnsubscribe(): bool
    {
        return match ($this) {
            self::Marketing, self::Newsletter, self::Promotion, self::ReEngagement => true,
            default => false,
        };
    }

    /**
     * Tailwind CSS badge styling.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Marketing => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            self::Property => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            self::Welcome => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300 border-sky-200 dark:border-sky-800',
            self::FollowUp => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            self::Inspection => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::Payment => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 border-green-200 dark:border-green-800',
            self::Newsletter => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            self::Promotion => 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-300 border-pink-200 dark:border-pink-800',
            self::Transactional => 'bg-slate-100 text-slate-800 dark:bg-slate-800/60 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            self::ReEngagement => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-200 dark:border-rose-800',
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
