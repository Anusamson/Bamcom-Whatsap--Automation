<?php

namespace App\Enums;

/**
 * Acquisition or attribution source for CRM contacts.
 */
enum LeadSource: string
{
    case WhatsApp = 'whatsapp';
    case Website = 'website';
    case Referral = 'referral';
    case SocialMedia = 'social_media';
    case GoogleAds = 'google_ads';
    case Event = 'event';
    case WalkIn = 'walk_in';
    case Direct = 'direct';
    case Other = 'other';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Website => 'Website',
            self::Referral => 'Referral',
            self::SocialMedia => 'Social Media',
            self::GoogleAds => 'Google Ads',
            self::Event => 'Event / Seminar',
            self::WalkIn => 'Walk-In',
            self::Direct => 'Direct Contact',
            self::Other => 'Other',
        };
    }

    /**
     * Tailwind CSS badge styling.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::WhatsApp => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 border-green-200 dark:border-green-800',
            self::Website => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            self::Referral => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            self::SocialMedia => 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-300 border-pink-200 dark:border-pink-800',
            self::GoogleAds => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300 border-sky-200 dark:border-sky-800',
            self::Event => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::WalkIn => 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300 border-teal-200 dark:border-teal-800',
            self::Direct => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            self::Other => 'bg-slate-100 text-slate-800 dark:bg-slate-800/60 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }

    /**
     * All lead source values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
